<?php declare(strict_types=1);

namespace DataTypeGeometryTest\DataType;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\DataType\GeometryCoordinates;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

class GeometryCoordinatesTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var GeometryCoordinates
     */
    protected $dataType;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataType = new GeometryCoordinates();
    }

    public function testGetName(): void
    {
        $this->assertSame('geometry:coordinates', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Geometric coordinates', $this->dataType->getLabel());
    }

    /**
     * @dataProvider validCoordinatesProvider
     */
    public function testIsValidWithValidCoordinates(string $value): void
    {
        $this->assertTrue($this->dataType->isValid(['@value' => $value]));
    }

    public function validCoordinatesProvider(): array
    {
        return [
            'integers' => ['100,200'],
            'floats' => ['100.5,200.3'],
            'zero' => ['0,0'],
            'negative' => ['-50,-100'],
            'mixed signs' => ['+50,-100.5'],
            'leading spaces' => [' 100 , 200'],
            'decimal only' => ['.5,.3'],
            'large numbers' => ['99999,88888'],
            'high precision' => ['1.23456789,9.87654321'],
        ];
    }

    /**
     * @dataProvider invalidCoordinatesProvider
     */
    public function testIsValidWithInvalidCoordinates($value, string $label = ''): void
    {
        $this->assertFalse(
            $this->dataType->isValid(['@value' => $value]),
            "Should be invalid: $label"
        );
    }

    public function invalidCoordinatesProvider(): array
    {
        return [
            'empty string' => ['', 'empty string'],
            'no comma' => ['100 200', 'no separator'],
            'text' => ['abc,def', 'non-numeric'],
            'single value' => ['100', 'single value'],
            'three values' => ['100,200,300', 'three values'],
            'wkt point' => ['POINT (100 200)', 'WKT format'],
        ];
    }

    public function testIsValidWithEmptyArray(): void
    {
        $this->assertFalse($this->dataType->isValid([]));
    }

    /**
     * @dataProvider geometryPointProvider
     */
    public function testGetGeometryPoint(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->dataType->getGeometryPoint($input));
    }

    public function geometryPointProvider(): array
    {
        return [
            'simple' => ['100,200', 'POINT (100 200)'],
            'floats' => ['1.5,2.5', 'POINT (1.5 2.5)'],
            'negative' => ['-50,-100', 'POINT (-50 -100)'],
            'zero' => ['0,0', 'POINT (0 0)'],
            'invalid' => ['not coordinates', null],
        ];
    }

    public function testGetGeometryPointKeepsXYOrder(): void
    {
        // Unlike geography (lat,lon → lon,lat), geometry keeps x,y order.
        $result = $this->dataType->getGeometryPoint('100,200');
        $this->assertSame('POINT (100 200)', $result);
    }

    public function testGetGeometryPointWithArray(): void
    {
        $result = $this->dataType->getGeometryPoint(['@value' => '100,200']);
        $this->assertSame('POINT (100 200)', $result);
    }

    public function testHydrate(): void
    {
        $valueObject = ['@value' => ' +100.5 , +200.3'];
        $value = $this->createMock(\Omeka\Entity\Value::class);
        $adapter = $this->createMock(\Omeka\Api\Adapter\AbstractEntityAdapter::class);

        $value->expects($this->once())
            ->method('setValue')
            ->with('100.5,200.3');
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

    public function testGetGeometryFromValueWithCoordinates(): void
    {
        $result = $this->dataType->getGeometryFromValue('100,200');
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
}
