<?php declare(strict_types=1);

namespace DataTypeGeometryTest\Module;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\Module;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

/**
 * Integration tests for Module::resolveCoordinatesFromTable()
 * (feature added in commit 8b15d427).
 *
 * Requires modules Table and Mapping installed.
 */
class ResolveCoordinatesFromTableTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \DataTypeGeometry\Module
     */
    protected $module;

    /**
     * @var int
     */
    protected $tableId;

    /**
     * @var array<int>
     */
    protected array $createdItemIds = [];

    public function setUp(): void
    {
        parent::setUp();

        $services = $this->getServiceLocator();
        $modules = $services->get('Omeka\ModuleManager');
        if (!$modules->getModule('Table') || $modules->getModule('Table')->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $this->markTestSkipped('Module Table is not active.');
        }
        if (!$modules->getModule('Mapping') || $modules->getModule('Mapping')->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $this->markTestSkipped('Module Mapping is not active.');
        }

        $this->loginAdmin();

        $this->connection = $services->get('Omeka\Connection');

        // Get real Module instance (Laminas ModuleManager).
        $laminas = $services->get('ModuleManager');
        $this->module = $laminas->getModule('DataTypeGeometry');
        $this->assertInstanceOf(Module::class, $this->module);
    }

    public function tearDown(): void
    {
        if ($this->createdItemIds) {
            $api = $this->api();
            foreach ($this->createdItemIds as $id) {
                try {
                    $api->delete('items', $id);
                } catch (\Throwable $e) {
                }
            }
        }
        if ($this->tableId) {
            $this->connection->executeStatement(
                'DELETE FROM `table_code` WHERE `table_id` = :id',
                ['id' => $this->tableId]
            );
            $this->connection->executeStatement(
                'DELETE FROM `tables` WHERE `id` = :id',
                ['id' => $this->tableId]
            );
        }
        parent::tearDown();
    }

    public function testResolveCreatesMappingFeatureForMatchingLiteral(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
            'Lyon' => '45.7640,4.8357',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT `label`, ST_AsText(`geography`) AS wkt FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertCount(1, $rows);
        $this->assertSame('Paris', $rows[0]['label']);
        $this->assertSame('POINT(2.294497 48.858252)', $rows[0]['wkt']);
    }

    public function testResolveCreatesCoordinatesValueForMatchingLiteral(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['record'],
            'from_properties' => ['dcterms:spatial'],
            // to_property defaults to first from_property.
        ]);

        $row = $this->connection->fetchAssociative(
            'SELECT `value`, `type` FROM `value` WHERE `resource_id` = :id AND `type` = "geography:coordinates"',
            ['id' => $itemId]
        );
        $this->assertIsArray($row);
        $this->assertSame('48.858252,2.294497', $row['value']);

        $dtg = $this->connection->fetchOne(
            'SELECT ST_AsText(`value`) FROM `data_type_geography` WHERE `resource_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame('POINT(2.294497 48.858252)', $dtg);
    }

    public function testResolveCreatesBothWhenMappingAndRecordChecked(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping', 'record'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $mf = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(1, (int) $mf);

        $v = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `value` WHERE `resource_id` = :id AND `type` = "geography:coordinates"',
            ['id' => $itemId]
        );
        $this->assertSame(1, (int) $v);
    }

    public function testResolveDeduplicatesExistingMappingFeature(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $args = [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['dcterms:spatial'],
        ];
        $this->invokeResolve([$itemId], $args);
        $this->invokeResolve([$itemId], $args);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(1, (int) $count);
    }

    public function testResolveDeduplicatesExistingCoordinateValue(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $args = [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['record'],
            'from_properties' => ['dcterms:spatial'],
        ];
        $this->invokeResolve([$itemId], $args);
        $this->invokeResolve([$itemId], $args);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `value` WHERE `resource_id` = :id AND `type` = "geography:coordinates"',
            ['id' => $itemId]
        );
        $this->assertSame(1, (int) $count);
    }

    public function testResolveIgnoresNonMatchingLiteral(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Atlantis');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping', 'record'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $mf = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(0, (int) $mf);
        $v = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `value` WHERE `resource_id` = :id AND `type` = "geography:coordinates"',
            ['id' => $itemId]
        );
        $this->assertSame(0, (int) $v);
    }

    public function testResolveNoOpWhenTableSlugMissing(): void
    {
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $this->invokeResolve([$itemId], [
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(0, (int) $count);
    }

    public function testResolveNoOpWhenTableSlugUnknown(): void
    {
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'does-not-exist',
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(0, (int) $count);
    }

    public function testResolveRespectsFromPropertiesFilter(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        // Literal "Paris" only on dcterms:description, not dcterms:spatial.
        $itemId = $this->createItemWithLiteral('dcterms:description', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['dcterms:spatial'],
        ]);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(0, (int) $count);
    }

    public function testResolveAcceptsAllProperties(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:description', 'Paris');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['mapping'],
            'from_properties' => ['all'],
        ]);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `mapping_feature` WHERE `item_id` = :id',
            ['id' => $itemId]
        );
        $this->assertSame(1, (int) $count);
    }

    public function testResolveUsesExplicitToProperty(): void
    {
        $this->createTable('test-geo-cities', [
            'Paris' => '48.858252,2.294497',
        ]);
        $itemId = $this->createItemWithLiteral('dcterms:spatial', 'Paris');

        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $toPid = $easyMeta->propertyId('dcterms:coverage');

        $this->invokeResolve([$itemId], [
            'resolve_table' => 'test-geo-cities',
            'manage_coordinates_features' => ['record'],
            'from_properties' => ['dcterms:spatial'],
            'to_property' => 'dcterms:coverage',
        ]);

        $row = $this->connection->fetchAssociative(
            'SELECT `property_id`, `value` FROM `value` WHERE `resource_id` = :id AND `type` = "geography:coordinates"',
            ['id' => $itemId]
        );
        $this->assertIsArray($row);
        $this->assertSame((int) $toPid, (int) $row['property_id']);
        $this->assertSame('48.858252,2.294497', $row['value']);
    }

    // ------------------------------------------------------------------
    // Preprocess validation tests (handleResourceBatchUpdatePreprocess).
    // ------------------------------------------------------------------

    public function testPreprocessRejectsTableOptionsWithoutResolveTable(): void
    {
        $post = [
            'geometry' => [
                'manage_coordinates_features' => ['mapping'],
                'from_properties' => ['dcterms:spatial'],
                // resolve_table missing.
            ],
        ];
        $data = ['geometry' => $post['geometry']];

        $event = $this->makeBatchPreprocessEvent($post, $data);
        $this->module->handleResourceBatchUpdatePreprocess($event);

        $this->assertArrayNotHasKey('geometry', $event->getParam('data'));
    }

    public function testPreprocessAcceptsTableOptionsWithResolveTable(): void
    {
        $this->createTable('test-geo-cities', ['Paris' => '48.858252,2.294497']);
        $post = [
            'geometry' => [
                'manage_coordinates_features' => ['mapping'],
                'from_properties' => ['dcterms:spatial'],
                'resolve_table' => 'test-geo-cities',
            ],
        ];
        $data = ['geometry' => $post['geometry']];

        $event = $this->makeBatchPreprocessEvent($post, $data);
        $this->module->handleResourceBatchUpdatePreprocess($event);

        $this->assertArrayHasKey('geometry', $event->getParam('data'));
        $this->assertSame(
            ['mapping'],
            $event->getParam('data')['geometry']['manage_coordinates_features']
        );
    }

    // ------------------------------------------------------------------
    // Helpers.
    // ------------------------------------------------------------------

    protected function invokeResolve(array $ids, array $geometry): void
    {
        $data = [
            'geometry' => $geometry + [
                'srid' => \DataTypeGeometry\DataType\Geography::DEFAULT_SRID,
            ],
        ];
        $ref = new \ReflectionMethod(Module::class, 'resolveCoordinatesFromTable');
        $ref->setAccessible(true);
        $ref->invoke($this->module, $ids, $data);
    }

    protected function createTable(string $slug, array $codeToLabel): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `tables` (`slug`, `is_associative`, `title`, `created`) VALUES (:slug, 1, :title, NOW())',
            ['slug' => $slug, 'title' => 'Test ' . $slug]
        );
        $this->tableId = (int) $this->connection->lastInsertId();
        foreach ($codeToLabel as $code => $label) {
            $this->connection->executeStatement(
                'INSERT INTO `table_code` (`table_id`, `code`, `label`) VALUES (:t, :c, :l)',
                ['t' => $this->tableId, 'c' => (string) $code, 'l' => (string) $label]
            );
        }
    }

    protected function createItemWithLiteral(string $term, string $value): int
    {
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $pid = $easyMeta->propertyId($term);
        $response = $this->api()->create('items', [
            'dcterms:title' => [[
                'type' => 'literal',
                'property_id' => $easyMeta->propertyId('dcterms:title'),
                '@value' => 'Test item ' . uniqid(),
            ]],
            $term => [[
                'type' => 'literal',
                'property_id' => $pid,
                '@value' => $value,
            ]],
        ]);
        $id = $response->getContent()->id();
        $this->createdItemIds[] = $id;
        return (int) $id;
    }

    protected function makeBatchPreprocessEvent(array $post, array $data): \Laminas\EventManager\Event
    {
        $services = $this->getServiceLocator();
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('items');

        $request = new \Omeka\Api\Request('batch_update', 'items');
        $request->setContent($post);

        $event = new \Laminas\EventManager\Event();
        $event->setTarget($adapter);
        $event->setParam('request', $request);
        $event->setParam('data', $data);
        $event->setParam('isPartial', true);

        return $event;
    }
}
