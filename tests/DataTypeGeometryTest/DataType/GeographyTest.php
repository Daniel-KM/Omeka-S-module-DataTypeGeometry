<?php declare(strict_types=1);

namespace DataTypeGeometryTest\DataType;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\DataType\Geography;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

class GeographyTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var Geography
     */
    protected $dataType;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataType = new Geography();
    }

    public function testGetName(): void
    {
        $this->assertSame('geography', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Geography', $this->dataType->getLabel());
    }

    public function testDefaultSrid(): void
    {
        $this->assertSame(4326, Geography::DEFAULT_SRID);
    }

    public function testGetEntityClass(): void
    {
        $this->assertSame(
            \DataTypeGeometry\Entity\DataTypeGeography::class,
            $this->dataType->getEntityClass()
        );
    }

    /**
     * @dataProvider validWktProvider
     */
    public function testIsValidWithValidWkt(string $value): void
    {
        $this->assertTrue(
            $this->dataType->isValid(['@value' => $value]),
            "Should be valid WKT: $value"
        );
    }

    public function validWktProvider(): array
    {
        return [
            'point' => ['POINT (2.294497 48.858252)'],
            'point lowercase' => ['point (2.294497 48.858252)'],
            'linestring' => ['LINESTRING (0 0, 1 1, 2 2)'],
            'polygon' => ['POLYGON ((0 0, 1 0, 1 1, 0 1, 0 0))'],
            'multipoint' => ['MULTIPOINT (0 0, 1 1)'],
            'point negative' => ['POINT (-74.0060 40.7128)'],
            'point zero' => ['POINT (0 0)'],
        ];
    }

    /**
     * @dataProvider invalidWktProvider
     */
    public function testIsValidWithInvalidWkt($value, string $label = ''): void
    {
        $this->assertFalse(
            $this->dataType->isValid(['@value' => $value]),
            "Should be invalid: $label"
        );
    }

    public function invalidWktProvider(): array
    {
        return [
            'empty string' => ['', 'empty string'],
            'coordinates' => ['48.858252,2.294497', 'coordinate format'],
            'text' => ['hello world', 'arbitrary text'],
            'incomplete point' => ['POINT (', 'incomplete WKT'],
            'missing parens' => ['POINT 2.294497 48.858252', 'missing parentheses'],
        ];
    }

    public function testIsValidWithEmptyArray(): void
    {
        $this->assertFalse($this->dataType->isValid([]));
    }

    public function testParseGeometryPoint(): void
    {
        $result = $this->dataType->parseGeometry('POINT (2.294497 48.858252)');
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertSame('POINT', $result['type']);
    }

    public function testParseGeometryLinestring(): void
    {
        $result = $this->dataType->parseGeometry('LINESTRING (0 0, 1 1, 2 2)');
        $this->assertNotNull($result);
        $this->assertSame('LINESTRING', $result['type']);
    }

    public function testParseGeometryPolygon(): void
    {
        $result = $this->dataType->parseGeometry('POLYGON ((0 0, 1 0, 1 1, 0 1, 0 0))');
        $this->assertNotNull($result);
        $this->assertSame('POLYGON', $result['type']);
    }

    public function testParseGeometryInvalid(): void
    {
        $result = $this->dataType->parseGeometry('not valid wkt');
        $this->assertNull($result);
    }

    public function testParseGeometryWithSrid(): void
    {
        $result = $this->dataType->parseGeometry('SRID=4326;POINT (2.294497 48.858252)');
        $this->assertNotNull($result);
        $this->assertSame('POINT', $result['type']);
        $this->assertSame(4326, $result['srid']);
    }

    public function testGetGeometryFromValueWithWkt(): void
    {
        $result = $this->dataType->getGeometryFromValue('POINT (2.294497 48.858252)');
        $this->assertInstanceOf(
            \LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface::class,
            $result
        );
    }

    public function testGetGeometryFromValueWithEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dataType->getGeometryFromValue('');
    }

    public function testGetGeometryFromValueWithInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dataType->getGeometryFromValue('not valid');
    }

    public function testGetGeometryFromValueWithArrayFormat(): void
    {
        $result = $this->dataType->getGeometryFromValue(['@value' => 'POINT (2.294497 48.858252)']);
        $this->assertInstanceOf(
            \LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface::class,
            $result
        );
    }

    public function testHydrate(): void
    {
        $valueObject = ['@value' => '  point (2.294497  48.858252)  '];
        $value = $this->createMock(\Omeka\Entity\Value::class);
        $adapter = $this->createMock(\Omeka\Api\Adapter\AbstractEntityAdapter::class);

        // Hydrate should uppercase and normalize spaces.
        $value->expects($this->once())
            ->method('setValue')
            ->with('POINT (2.294497 48.858252)');
        $value->expects($this->once())
            ->method('setLang')
            ->with(null);
        $value->expects($this->once())
            ->method('setUri')
            ->with(null);
        $value->expects($this->once())
            ->method('setValueResource')
            ->with(null);

        $this->dataType->hydrate($valueObject, $value, $adapter);
    }
}
