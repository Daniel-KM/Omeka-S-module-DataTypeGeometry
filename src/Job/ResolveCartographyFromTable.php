<?php declare(strict_types=1);

namespace DataTypeGeometry\Job;

use DataTypeGeometry\DataType\Geography;
use Omeka\Job\AbstractJob;

/**
 * Resolve literal values of items from a Table module table and create
 * Cartography annotations (target with rdf:value of type geography).
 *
 * Args:
 * - item_ids (int[]): items to process.
 * - resolve_table (string): slug of the table (code = literal, label =
 * "lat,lon"). - from_properties (string[]): property terms to scan, or ["all"].
 * - srid (int): stored on the wkt point.
 */
class ResolveCartographyFromTable extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $connection = $services->get('Omeka\Connection');
        $easyMeta = $services->get('Common\EasyMeta');

        $itemIds = array_values(array_filter(array_map('intval', (array) $this->getArg('item_ids', []))));
        $tableSlug = (string) $this->getArg('resolve_table', '');
        $fromProperties = (array) $this->getArg('from_properties', []);
        $srid = (int) $this->getArg('srid', Geography::DEFAULT_SRID);

        if (!$itemIds || !$tableSlug) {
            return;
        }

        $tableId = (int) $connection->fetchOne(
            'SELECT `id` FROM `tables` WHERE `slug` = :slug LIMIT 1',
            ['slug' => $tableSlug]
        );
        if (!$tableId) {
            $logger->err(sprintf('Table "%s" not found.', $tableSlug));
            return;
        }

        $propertyIds = (!$fromProperties || in_array('all', $fromProperties, true))
            ? []
            : array_values($easyMeta->propertyIds($fromProperties));

        // Fetch literal values for the items that match a code in the table.
        // One row per (item, literal, resolved label).
        $sqlWhere = '';
        $bind = [
            'item_ids' => $itemIds,
            'table_id' => $tableId,
        ];
        $types = [
            'item_ids' => $connection::PARAM_INT_ARRAY,
            'table_id' => \Doctrine\DBAL\ParameterType::INTEGER,
        ];
        if ($propertyIds) {
            $sqlWhere = 'AND `value`.`property_id` IN (:property_ids)';
            $bind['property_ids'] = $propertyIds;
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        }

        $sql = <<<SQL
            SELECT DISTINCT
                `value`.`resource_id` AS item_id,
                `value`.`value` AS code,
                `tc`.`label` AS coords
            FROM `value`
            INNER JOIN `table_code` `tc`
                ON `tc`.`code` = `value`.`value`
                AND `tc`.`table_id` = :table_id
            WHERE
                `value`.`resource_id` IN (:item_ids)
                AND `value`.`type` = "literal"
                $sqlWhere
            ;
            SQL;
        $rows = $connection->fetchAllAssociative($sql, $bind, $types);
        if (!$rows) {
            return;
        }

        $resourceClassId = $easyMeta->resourceClassId('oa:Annotation');
        $propHasSource = $easyMeta->propertyId('oa:hasSource');
        $propFormat = $easyMeta->propertyId('dcterms:format');
        $propRdfValue = $easyMeta->propertyId('rdf:value');

        // Find the customvocab used for annotation target dcterms:format.
        $customVocabId = null;
        try {
            $cv = $api->search('custom_vocabs', ['label' => 'Annotation Target dcterms:format'])->getContent();
            if ($cv) {
                $customVocabId = reset($cv)->id();
            }
        } catch (\Throwable $e) {
        }
        $formatType = $customVocabId ? ('customvocab:' . $customVocabId) : 'literal';

        $created = 0;
        foreach ($rows as $row) {
            if ($this->shouldStop()) {
                break;
            }
            $coords = array_map('trim', explode(',', (string) $row['coords'], 2));
            if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                continue;
            }
            [$lat, $lon] = $coords;
            $wkt = sprintf('POINT (%s %s)', $lon, $lat);

            // Dedup: check if an annotation already exists for this item +
            // identical WKT.
            $exists = $this->annotationExists((int) $row['item_id'], $wkt, $propHasSource, $propRdfValue, $connection);
            if ($exists) {
                continue;
            }

            try {
                $api->create('annotations', [
                    'o:is_public' => true,
                    'o:resource_class' => $resourceClassId ? ['o:id' => $resourceClassId] : null,
                    'oa:hasBody' => [],
                    'oa:hasTarget' => [[
                        'oa:hasSource' => [[
                            'property_id' => $propHasSource,
                            'type' => 'resource',
                            'value_resource_id' => (int) $row['item_id'],
                        ]],
                        'dcterms:format' => [[
                            'property_id' => $propFormat,
                            'type' => $formatType,
                            '@value' => 'application/wkt',
                        ]],
                        'rdf:value' => [[
                            'property_id' => $propRdfValue,
                            'type' => 'geography',
                            '@value' => $wkt,
                        ]],
                    ]],
                ]);
                ++$created;
            } catch (\Throwable $e) {
                $logger->err(sprintf(
                    'Cartography annotation failed for item #%d (%s): %s',
                    $row['item_id'],
                    $row['code'],
                    $e->getMessage()
                ));
            }
        }

        $logger->info(sprintf('Created %d Cartography annotations from table "%s".', $created, $tableSlug));
    }

    protected function annotationExists(
        int $itemId,
        string $wkt,
        int $propHasSource,
        int $propRdfValue,
        \Doctrine\DBAL\Connection $connection
    ): bool {
        $sql = <<<SQL
            SELECT 1
            FROM `resource` `ann`
            INNER JOIN `value` `src` ON `src`.`resource_id` = `ann`.`id`
                AND `src`.`property_id` = :prop_source
                AND `src`.`value_resource_id` = :item_id
            INNER JOIN `value` `geo` ON `geo`.`resource_id` = `ann`.`id`
                AND `geo`.`property_id` = :prop_rdf
                AND `geo`.`type` = "geography"
                AND `geo`.`value` = :wkt
            WHERE `ann`.`resource_type` = "Annotate\\\\Entity\\\\Annotation"
            LIMIT 1
            ;
            SQL;
        return (bool) $connection->fetchOne($sql, [
            'prop_source' => $propHasSource,
            'item_id' => $itemId,
            'prop_rdf' => $propRdfValue,
            'wkt' => $wkt,
        ]);
    }
}
