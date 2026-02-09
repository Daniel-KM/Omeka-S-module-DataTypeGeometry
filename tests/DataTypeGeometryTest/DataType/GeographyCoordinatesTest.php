<?php declare(strict_types=1);

namespace DataTypeGeometryTest\DataType;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\DataType\GeographyCoordinates;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

class GeographyCoordinatesTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var GeographyCoordinates
     */
    protected $dataType;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataType = new GeographyCoordinates();
    }

    public function testGetName(): void
    {
        $this->assertSame('geography:coordinates', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Geographic coordinates', $this->dataType->getLabel());
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
            'paris' => ['48.858252,2.294497'],
            'zero' => ['0,0'],
            'negative lat' => ['-33.8688,151.2093'],
            'negative lon' => ['40.7128,-74.0060'],
            'both negative' => ['-33.4489,-70.6693'],
            'max lat' => ['90,0'],
            'min lat' => ['-90,0'],
            'max lon' => ['0,180'],
            'min lon' => ['0,-180'],
            'with spaces' => [' 48.858252 , 2.294497 '],
            'with plus sign' => ['+48.858252,+2.294497'],
            'integer coords' => ['48,2'],
            'high precision' => ['48.12345678,2.12345678'],
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
            'lat too high' => ['91,0', 'latitude > 90'],
            'lat too low' => ['-91,0', 'latitude < -90'],
            'lon too high' => ['0,181', 'longitude > 180'],
            'lon too low' => ['0,-181', 'longitude < -180'],
            'empty string' => ['', 'empty string'],
            'no comma' => ['48.858252 2.294497', 'no separator'],
            'text' => ['abc,def', 'non-numeric'],
            'single value' => ['48.858252', 'single value'],
            'three values' => ['48.858252,2.294497,0', 'three values'],
            'wkt point' => ['POINT (2.294497 48.858252)', 'WKT format'],
        ];
    }

    public function testIsValidWithEmptyArray(): void
    {
        $this->assertFalse($this->dataType->isValid([]));
    }

    public function testIsValidWithMissingValue(): void
    {
        $this->assertFalse($this->dataType->isValid(['type' => 'geography:coordinates']));
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
            'simple' => ['48.858252,2.294497', 'POINT (2.294497 48.858252)'],
            'negative' => ['-33.8688,151.2093', 'POINT (151.2093 -33.8688)'],
            'zero' => ['0,0', 'POINT (0 0)'],
            'invalid' => ['not coordinates', null],
        ];
    }

    public function testGetGeometryPointWithArray(): void
    {
        $result = $this->dataType->getGeometryPoint(['@value' => '48.858252,2.294497']);
        $this->assertSame('POINT (2.294497 48.858252)', $result);
    }

    public function testGetGeometryPointSwapsLatLon(): void
    {
        // Geographic coordinates are lat,lon but WKT uses lon,lat (x,y).
        $result = $this->dataType->getGeometryPoint('48.858252,2.294497');
        $this->assertSame('POINT (2.294497 48.858252)', $result);
        // Verify longitude (x) comes first in WKT.
        $this->assertStringContainsString('2.294497 48.858252', $result);
    }

    public function testHydrate(): void
    {
        $valueObject = ['@value' => ' +48.858252 , +2.294497 '];
        $value = $this->createMock(\Omeka\Entity\Value::class);
        $adapter = $this->createMock(\Omeka\Api\Adapter\AbstractEntityAdapter::class);

        // Hydrate should normalize: remove leading +, trim spaces.
        $value->expects($this->once())
            ->method('setValue')
            ->with('48.858252,2.294497');
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
        $result = $this->dataType->getGeometryFromValue('48.858252,2.294497');
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

    public function testGetGeometryFromValueWithWktArray(): void
    {
        // Array format only works with WKT, not coordinate format.
        $result = $this->dataType->getGeometryFromValue(['@value' => 'POINT (2.294497 48.858252)']);
        $this->assertInstanceOf(
            \LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface::class,
            $result
        );
    }
}
