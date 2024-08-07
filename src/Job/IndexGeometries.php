<?php declare(strict_types=1);

namespace DataTypeGeometry\Job;

use Omeka\Job\AbstractJob;

class IndexGeometries extends AbstractJob
{
    /**
     * @var \Omeka\Mvc\Controller\Plugin\Logger $logger
     */
    protected $logger;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Api $api
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection $connection
     */
    protected $connection;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $this->api = $services->get('ControllerPluginManager')->get('api');
        $this->connection = $services->get('Omeka\Connection');

        $processMode = $this->getArg('process_mode');
        $processModes = [
            'common',
            'resources reindex',
            'resources geometry',
            'resources geography',
            'annotations reindex',
            'annotations geometry',
            'annotations geography',
            'cartography',
            'check',
            'check geometry',
            'check geography',
            'fix linestring',
            'truncate',
        ];
        if (!in_array($processMode, $processModes)) {
            $this->logger->info(
                'Indexing geometries stopped: no mode selected.' // @translate
            );
            return;
        }

        switch ($processMode) {
            case 'resources reindex':
            case 'resources geometry':
            case 'resources geography':
            case 'annotations reindex':
            case 'annotations geometry':
            case 'annotations geography':
                $updateValues = strpos($processMode, 'reindex') === false;
                $isAnnotation = strpos($processMode, 'annotations') !== false;
                $isGeography = strpos($processMode, 'geography') !== false;
                if (!$this->checkBefore([['isGeography' => $isGeography]])) {
                    return;
                }
                $this->reindex([
                    'updateValues' => $updateValues,
                    'isAnnotation' => $isAnnotation,
                    'isGeography' => $isGeography,
                ]);
                break;
            case 'cartography':
                if (!$this->checkBefore([['isGeography' => null]])) {
                    return;
                }
                $this->indexCartographyTargets();
                break;
            case 'check':
                $this->check([
                    'isGeography' => null,
                ]);
                break;
            case 'check geometry':
            case 'check geography':
                $isGeography = strpos($processMode, 'geography') !== false;
                $this->check([
                    'isGeography' => $isGeography,
                ]);
                break;
            case 'fix linestring':
                $this->fix(['fix' => ['linestring']]);
                break;
            case 'truncate':
                $this->truncate();
                break;
            case 'common':
                $this->truncate();
                $this->reindex([
                    'updateValues' => true,
                    'isAnnotation' => false,
                    'isGeography' => false,
                ]);
                $this->reindex([
                    'updateValues' => true,
                    'isAnnotation' => true,
                    'isGeography' => false,
                ]);
                $this->indexCartographyTargets();
                break;
        }
    }

    protected function truncate(): void
    {
        $sql = <<<'SQL'
SET foreign_key_checks = 0;
TRUNCATE TABLE `data_type_geography`;
TRUNCATE TABLE `data_type_geometry`;
SET foreign_key_checks = 1;
SQL;
        $this->connection->executeStatement($sql);
        $this->logger->info(
            'Tables "data_type_geometry" and "data_type_geography" were truncated.' // @translate
        );
    }

    protected function reindex(array $options): void
    {
        $logger = $this->logger;
        $api = $this->api;
        $connection = $this->connection;

        $updateValues = $options['updateValues'];
        $isAnnotation = $options['isAnnotation'];
        $isGeography = $options['isGeography'];

        $resourceType = $isAnnotation
            ? 'annotations'
            : 'resources';
        $resourceTypes = $isAnnotation
            ? '"Annotate\\\\Entity\\\\Annotation", "Annotate\\\\Entity\\\\AnnotationBody", "Annotate\\\\Entity\\\\AnnotationTarget"'
            : '"Omeka\\\\Entity\\\\Item", "Omeka\\\\Entity\\\\ItemSet", "Omeka\\\\Entity\\\\Media"';
        $dataType = $isGeography
            ? 'geography'
            : 'geometry';
        $table = $isGeography
            ? 'data_type_geography'
            : 'data_type_geometry';
        $defaultSrid = $this->getServiceLocator()->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', 4236);

        if ($updateValues) {
            $sql = <<<SQL
UPDATE `value`
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
SET `type` = "$dataType", `lang` = NULL, `value_resource_id` = NULL, `uri` = NULL
WHERE `resource`.`resource_type` IN ($resourceTypes)
AND `value`.`type` IN ("geometry", "geography");
SQL;
            $connection->executeStatement($sql);
            $logger->info(
                'All geometric values for {resource_type} have now the data type "{data_type}".', // @translate
                ['resource_type' => $resourceType, 'data_type' => $dataType]
            );
        }

        $tables = [
            // 'geography:coordinates' => 'data_type_geography' ,
            // 'geometry:coordinates' => 'data_type_geometry' ,
            // 'geometry:position' => 'data_type_geometry' ,
            'geography' => 'data_type_geography' ,
            'geometry' => 'data_type_geometry' ,
        ];

        // Remove existing index for resources or annotations in all tables.
        foreach ($tables as $dataType => $table) {
            $srid = $table === 'data_type_geography' ? $defaultSrid : 0;

            $sql = <<<SQL
DELETE FROM `$table`
WHERE EXISTS (
    SELECT 1
    FROM `resource`
    WHERE `resource`.`id` = `$table`.`resource_id`
    AND `resource`.`resource_type` IN ($resourceTypes)
);
SQL;
            $connection->executeStatement($sql);
        }

        // When the values are forced to a data type, all data types are indexed
        // in one table.
        if (!$updateValues) {
            $dataTypes = '"geography", "geometry"';
            $tables = [$dataType => $tables[$dataType]];
        }

        foreach ($tables as $dataType => $table) {
            // When the values are simply reindexed, each data type is indexed
            // in its table.
            if ($updateValues) {
                $dataTypes = '"' . $dataType . '"';
            }

            $srid = $table === 'data_type_geography' ? $defaultSrid : 0;

            // Keep the existing srid in all cases, even for geometries.
            $sql = <<<SQL
INSERT INTO `$table` (`resource_id`, `property_id`, `value`)
SELECT `resource_id`, `property_id`, GeomFromText(`value`, $srid)
FROM `value`
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
WHERE `resource`.`resource_type` IN ($resourceTypes)
AND `value`.`type` IN ($dataTypes)
AND `value`.`value` NOT LIKE "SRID%"
ORDER BY `value`.`id` ASC;
SQL;
            $connection->executeStatement($sql);
            $sql = <<<SQL
INSERT INTO `$table` (`resource_id`, `property_id`, `value`)
SELECT `resource_id`, `property_id`, GeomFromText(TRIM(SUBSTRING_INDEX(`value`, ';', -1)), SUBSTRING_INDEX(SUBSTRING_INDEX(`value`, ';', 1), '=', -1))
FROM `value`
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
WHERE `resource`.`resource_type` IN ($resourceTypes)
AND `value`.`type` IN ($dataTypes)
AND `value`.`value` LIKE "SRID%"
ORDER BY `value`.`id` ASC;
SQL;
            $connection->executeStatement($sql);
        }

        $logger->info(
            'Geometries were indexed.' // @translate
        );
    }

    protected function indexCartographyTargets(): void
    {
        $logger = $this->logger;
        $api = $this->api;
        $connection = $this->connection;

        // All annotation targets that have wkt and a media as selector are
        // "geometry", and other wkt targets are "geography".

        $property = 'rdf:value';
        $rdfValue = $api->searchOne('properties', ['term' => $property])->getContent();
        if (!$rdfValue) {
            $this->logger->err(
                'The property "rdf:value" was not found. Resinstall vocabulary "rdf".' // @translate
            );
            return;
        }
        $rdfValue = $rdfValue->id();

        $property = 'oa:hasSelector';
        $oaHasSelector = $api->searchOne('properties', ['term' => $property])->getContent();
        if (!$oaHasSelector) {
            $this->logger->err(
                'The property "oa:hasSelector" was not found. Resinstall vocabulary "OpenAnnotation".' // @translate
            );
            return;
        }
        $oaHasSelector = $oaHasSelector->id();

        $defaultSrid = $this->getServiceLocator()->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', 4236);

        // Set all targets wkt a geography (only for property "rdf:value").
        $sql = <<<SQL
UPDATE value
INNER JOIN annotation_target ON annotation_target.id = value.resource_id
INNER JOIN resource ON resource.id = value.resource_id
SET type = "geography", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE resource.resource_type = "Annotate\\\\Entity\\\\AnnotationTarget"
AND value.type IN ("geometry", "geography")
AND value.property_id = $rdfValue;
SQL;

        $connection->executeStatement($sql);
        // Set all media targets (via oa:hasSelector) wkt a geometry (only for
        // "rdf:value").
        $sql = <<<SQL
UPDATE `value`
INNER JOIN `annotation_target` ON annotation_target.id = value.resource_id
SET type = "geometry", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE value.type IN ("geometry", "geography")
AND value.property_id = $rdfValue
AND value.resource_id IN (
  SELECT resource_id FROM (
    SELECT DISTINCT value.resource_id
    FROM `value`
    INNER JOIN `annotation_target` ON annotation_target.id = value.resource_id
    INNER JOIN `resource` ON resource.id = value.value_resource_id
        AND resource.resource_type = "Omeka\\\\Entity\\\\Media"
    WHERE value.property_id = $oaHasSelector
    AND value.value_resource_id IS NOT NULL
  ) AS w
);
SQL;
        $connection->executeStatement($sql);
        $logger->info(
            'All geometric values for cartographic annotation targets were updated according to their type (describe or locate).' // @translate
        );

        $tables = [
            'geography' => 'data_type_geography' ,
            'geometry' => 'data_type_geometry' ,
        ];
        foreach ($tables as $dataType => $table) {
            $srid = $table === 'data_type_geography' ? $defaultSrid : 0;

            // Remove existing index for annotation targets only.
            $sql = <<<SQL
DELETE FROM `$table`
WHERE EXISTS (
    SELECT 1
    FROM annotation_target
    INNER JOIN `resource` ON resource.id = annotation_target.id
    WHERE resource.resource_type = "Annotate\\\\Entity\\\\AnnotationTarget"
    AND resource.id = $table.resource_id
    AND $table.property_id = $rdfValue
);
SQL;
            $connection->executeStatement($sql);

            // Index annotation targets.
            // Keep the existing srid in all cases, even for geometries.
            $sql = <<<SQL
INSERT INTO `$table` (resource_id, property_id, value)
SELECT resource_id, property_id, GeomFromText(value, $srid)
FROM value
INNER JOIN annotation_target ON annotation_target.id = value.resource_id
INNER JOIN resource ON resource.id = value.resource_id
WHERE resource.resource_type = "Annotate\\\\Entity\\\\AnnotationTarget"
AND value.type = "$dataType"
AND value.property_id = $rdfValue
AND value.value NOT LIKE "SRID%"
ORDER BY value.id ASC;
SQL;
            $connection->executeStatement($sql);
            $sql = <<<SQL
INSERT INTO `$table` (resource_id, property_id, value)
SELECT resource_id, property_id, GeomFromText(TRIM(SUBSTRING_INDEX(value, ';', -1)), SUBSTRING_INDEX(SUBSTRING_INDEX(value, ';', 1), '=', -1))
FROM value
INNER JOIN annotation_target ON annotation_target.id = value.resource_id
INNER JOIN resource ON resource.id = value.resource_id
WHERE resource.resource_type = "Annotate\\\\Entity\\\\AnnotationTarget"
AND value.type = "$dataType"
AND value.property_id = $rdfValue
AND value.value LIKE "SRID%"
ORDER BY value.id ASC;
SQL;
            $connection->executeStatement($sql);
        }

        $logger->info(
            'Geometries were indexed for annotation targets.' // @translate
        );
    }

    /**
     * Check before a process.
     */
    protected function checkBefore(array $options): bool
    {
        $success = $this->check($options);
        if (!$success) {
            $this->logger->err(
                'Cannot process: there are errors in your original values. Try to fix them first.' // @translate
            );
        }
        return $success;
    }

    /**
     * Check if geo values are well-formed.
     */
    protected function check(array $options): bool
    {
        $logger = $this->logger;
        $api = $this->api;
        $connection = $this->connection;

        $isGeography = $options['isGeography'];
        if (is_null($isGeography)) {
            $dataTypes = [
                'geography',
                'geometry',
            ];
        } else {
            $dataTypes = $isGeography
                ? ['geography']
                : ['geometry'];
        }

        $success = true;

        foreach ($dataTypes as $dataType) {
            $sql = <<<SQL
SELECT COUNT(value.id)
FROM value
INNER JOIN resource ON resource.id = value.resource_id
WHERE value.type = "$dataType"
AND (
    value.value LIKE "POINT%,%"
    OR (value.value LIKE "LINESTRING%" AND value.value NOT LIKE "%,%")
    OR (value.value LIKE "POLYGON%" AND value.value NOT LIKE "%,%,%,%")
)
ORDER BY value.id ASC;
SQL;

            $total = $connection->executeQuery($sql)->fetchOne();

            if (!$total) {
                $logger->notice(
                    'There seems no issues in {data_type}.', // @translate
                    ['data_type' => in_array($dataType, ['geography', 'geography:coordinates']) ? 'geographies' : 'geometries']
                );
                continue;
            }

            $success = false;

            $sql = <<<SQL
SELECT
    value.id AS "value id",
    resource.resource_type as "resource type",
    value.resource_id as "resource id",
    CASE
        WHEN value.value LIKE "POINT%,%" THEN "Bad point"
        WHEN (value.value LIKE "LINESTRING%" AND value.value NOT LIKE "%,%") THEN "Line requires at least two points"
        WHEN (value.value LIKE "POLYGON%" AND value.value NOT LIKE "%,%,%,%") THEN "Polygon requires at least four points"
    END AS "issue",
    value.value AS "value"
FROM value
INNER JOIN resource ON resource.id = value.resource_id
WHERE value.type = "$dataType"
AND (
    value.value LIKE "POINT%,%"
    OR (value.value LIKE "LINESTRING%" AND value.value NOT LIKE "%,%")
    OR (value.value LIKE "POLYGON%" AND value.value NOT LIKE "%,%,%,%")
)
ORDER BY value.id ASC;
SQL;

            $logger->warn(
                'These {count} {data_type} have issues.', // @translate
                ['count' => $total, 'data_type' => in_array($dataType, ['geography', 'geography:coordinates']) ? 'geographies' : 'geometries']
            );

            $stmt = $connection->query($sql);
            while ($row = $stmt->fetch()) {
                $logger->warn(
                    json_encode($row)
                );
            }
        }

        return $success;
    }

    protected function fix(array $options): void
    {
        $logger = $this->logger;
        $api = $this->api;
        $connection = $this->connection;

        $fixes = $options['fix'];
        foreach ($fixes as $fix) {
            switch ($fix) {
                case 'linestring':
                    $sql = <<<SQL
UPDATE value
INNER JOIN resource ON resource.id = value.resource_id
SET value = REPLACE(value, "LINESTRING", "POINT")
WHERE
    value.value LIKE "LINESTRING%" AND value.value NOT LIKE "%,%"
SQL;

                    $total = $connection->executeStatement($sql);
                    if ($total) {
                        $logger->notice(
                            '{count} bad "linestring()" were replaced by "point()".', // @translate
                            ['count' => $total]
                        );
                    } else {
                        $logger->notice(
                            'No bad "linestring()" found.' // @translate
                        );
                    }
                    break;
            }
        }
    }
}
