<?php declare(strict_types=1);

namespace DataTypeGeometryTest\DataType;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\DataType\Geometry;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

class GeometryTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var Geometry
     */
    protected $dataType;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataType = new Geometry();
    }

    public function testGetName(): void
    {
        $this->assertSame('geometry', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Geometry', $this->dataType->getLabel());
    }

    public function testGetEntityClass(): void
    {
        $this->assertSame(
            \DataTypeGeometry\Entity\DataTypeGeometry::class,
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
            'point' => ['POINT (100 200)'],
            'point lowercase' => ['point (100 200)'],
            'point negative' => ['POINT (-50 -100)'],
            'point zero' => ['POINT (0 0)'],
            'point float' => ['POINT (1.5 2.5)'],
            'linestring' => ['LINESTRING (0 0, 100 100, 200 0)'],
            'polygon' => ['POLYGON ((0 0, 100 0, 100 100, 0 100, 0 0))'],
            'multipoint' => ['MULTIPOINT (0 0, 100 100)'],
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
            'coordinates' => ['100,200', 'coordinate format'],
            'text' => ['hello world', 'arbitrary text'],
            'incomplete' => ['POINT (', 'incomplete WKT'],
        ];
    }

    public function testIsValidWithEmptyArray(): void
    {
        $this->assertFalse($this->dataType->isValid([]));
    }

    public function testParseGeometryPoint(): void
    {
        $result = $this->dataType->parseGeometry('POINT (100 200)');
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertSame('POINT', $result['type']);
    }

    public function testParseGeometryInvalid(): void
    {
        $result = $this->dataType->parseGeometry('not valid wkt');
        $this->assertNull($result);
    }

    public function testGetGeometryFromValueWithWkt(): void
    {
        $result = $this->dataType->getGeometryFromValue('POINT (100 200)');
        $this->assertInstanceOf(
            \LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface::class,
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
        $result = $this->dataType->getGeometryFromValue(['@value' => 'POINT (100 200)']);
        $this->assertInstanceOf(
            \LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface::class,
            $result
        );
    }

    public function testHydrate(): void
    {
        $valueObject = ['@value' => '  point (100  200)  '];
        $value = $this->createMock(\Omeka\Entity\Value::class);
        $adapter = $this->createMock(\Omeka\Api\Adapter\AbstractEntityAdapter::class);

        $value->expects($this->once())
            ->method('setValue')
            ->with('POINT (100 200)');
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
