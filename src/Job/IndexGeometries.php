<?php
namespace DataTypeGeometry\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class IndexGeometries extends AbstractJob
{
    public function perform()
    {
        /**
         * @var \Omeka\Mvc\Controller\Plugin\Logger $logger
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         * @var \Doctrine\DBAL\Connection $connection
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('ControllerPluginManager')->get('api');
        $connection = $services->get('Omeka\Connection');

        $processMode = $this->getArg('process_mode');
        $processModes = [
            'resources reindex',
            'resources geometry',
            'resources geography',
            'annotations reindex',
            'annotations geometry',
            'annotations geography',
            'cartography',
            'truncate',
        ];
        if (!in_array($processMode, $processModes)) {
            $logger->info(new Message(
                'Indexing geometries stopped: no mode selected.' // @translate
            ));
            return;
        }

        if ($processMode === 'truncate') {
            $sql = <<<'SQL'
SET foreign_key_checks = 0;
TRUNCATE TABLE `data_type_geography`;
TRUNCATE TABLE `data_type_geometry`;
SET foreign_key_checks = 1;
SQL;
            $connection->exec($sql);
            $logger->info(
                'Tables "data_type_geometry" and "data_type_geography" were truncated.' // @translate
            );
            return;
        }

        if ($processMode === 'cartography') {
            $this->indexCartographyTargets();
            return;
        }

        $updateValues = strpos($processMode, 'reindex') === false;
        $isAnnotation = strpos($processMode, 'annotations') !== false;
        $isGeography = strpos($processMode, 'geography') !== false;
        $resourceType = $isAnnotation
            ? 'annotations'
            : 'resources';
        $resourceTypes = $isAnnotation
            ? '"Annotate\\\\Entity\\\\Annotation", "Annotate\\\\Entity\\\\AnnotationBody", "Annotate\\\\Entity\\\\AnnotationTarget"'
            : '"Omeka\\\\Entity\\\\Item", "Omeka\\\\Entity\\\\ItemSet", "Omeka\\\\Entity\\\\Media"';
        $dataType = $isGeography
            ? 'geometry:geography'
            : 'geometry:geometry';
        $table = $isGeography
            ? 'data_type_geography'
            : 'data_type_geometry';
        $defaultSrid = $services->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', 4236);

        if ($updateValues) {
            $sql = <<<SQL
UPDATE value
INNER JOIN resource ON resource.id = value.resource_id
SET type = "$dataType", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE resource.resource_type IN ($resourceTypes)
AND value.type IN ("geometry:geometry", "geometry:geography");
SQL;
            $connection->exec($sql);
            $logger->info(new Message(
                'All geometric values for %s have now the data type "%s".', // @translate
                $resourceType, $dataType
            ));
        }

        $tables = [
            'geometry:geography' => 'data_type_geography' ,
            'geometry:geometry' => 'data_type_geometry' ,
        ];

        // Remove existing index for resources or annotations in all tables.
        foreach ($tables as $dataType => $table) {
            $srid = $table === 'data_type_geography' ? $defaultSrid : 0;

            $sql = <<<SQL
DELETE FROM `$table`
WHERE EXISTS (
    SELECT 1
    FROM `resource`
    WHERE resource.id = $table.resource_id
    AND resource.resource_type IN ($resourceTypes)
);
SQL;
            $connection->exec($sql);
        }

        // When the values are forced to a data type, all data types are indexed
        // in one table.
        if (!$updateValues) {
            $dataTypes = '"geometry:geography", "geometry:geometry"';
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
INSERT INTO `$table` (resource_id, property_id, value)
SELECT resource_id, property_id, GeomFromText(value, $srid)
FROM value
INNER JOIN resource ON resource.id = value.resource_id
WHERE resource.resource_type IN ($resourceTypes)
AND value.type IN ($dataTypes)
AND value.value NOT LIKE "SRID%"
ORDER BY value.id ASC;
SQL;
            $connection->exec($sql);
            $sql = <<<SQL
INSERT INTO `$table` (resource_id, property_id, value)
SELECT resource_id, property_id, GeomFromText(TRIM(SUBSTRING_INDEX(value, ';', -1)), SUBSTRING_INDEX(SUBSTRING_INDEX(value, ';', 1), '=', -1))
FROM value
INNER JOIN resource ON resource.id = value.resource_id
WHERE resource.resource_type IN ($resourceTypes)
AND value.type IN ($dataTypes)
AND value.value LIKE "SRID%"
ORDER BY value.id ASC;
SQL;
            $connection->exec($sql);
        }

        $logger->info(new Message(
            'Geometries were indexed.' // @translate
        ));
    }

    protected function indexCartographyTargets()
    {
        /**
         * @var \Omeka\Mvc\Controller\Plugin\Logger $logger
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         * @var \Doctrine\DBAL\Connection $connection
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('ControllerPluginManager')->get('api');
        $connection = $services->get('Omeka\Connection');

        // All annotation targets that have wkt and a media as selector are
        // "geometry:geometry", and other wkt targets are "geometry:geography".

        $property = 'rdf:value';
        $rdfValue = $api->searchOne('properties', ['term' => $property])->getContent()->id();
        $property = 'oa:hasSelector';
        $oaHasSelector = $api->searchOne('properties', ['term' => $property])->getContent()->id();
        $defaultSrid = $services->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', 4236);

        // Set all targets wkt a geography (only for property "rdf:value").
        $sql = <<<SQL
UPDATE value
INNER JOIN annotation_target ON annotation_target.id = value.resource_id
INNER JOIN resource ON resource.id = value.resource_id
SET type = "geometry:geography", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE resource.resource_type = "Annotate\\\\Entity\\\\AnnotationTarget"
AND value.type IN ("geometry:geometry", "geometry:geography")
AND value.property_id = $rdfValue;
SQL;

        $connection->exec($sql);
        // Set all media targets (via oa:hasSelector) wkt a geometry (only for
        // "rdf:value").
        $sql = <<<SQL
UPDATE `value`
INNER JOIN `annotation_target` ON annotation_target.id = value.resource_id
SET type = "geometry:geometry", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE value.type IN ("geometry:geometry", "geometry:geography")
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
        $connection->exec($sql);
        $logger->info(new Message(
            'All geometric values for cartographic annotation targets were updated according to their type (describe or locate).' // @translate
        ));

        $tables = [
            'geometry:geography' => 'data_type_geography' ,
            'geometry:geometry' => 'data_type_geometry' ,
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
            $connection->exec($sql);

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
            $connection->exec($sql);
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
            $connection->exec($sql);
        }

        $logger->info(new Message(
            'Geometries were indexed for annotation targets.' // @translate
        ));
    }
}
