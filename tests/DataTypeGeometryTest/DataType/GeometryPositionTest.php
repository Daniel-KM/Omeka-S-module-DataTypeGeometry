<?php declare(strict_types=1);

namespace DataTypeGeometryTest\DataType;

use CommonTest\AbstractHttpControllerTestCase;
use DataTypeGeometry\DataType\GeometryPosition;
use DataTypeGeometryTest\DataTypeGeometryTestTrait;

class GeometryPositionTest extends AbstractHttpControllerTestCase
{
    use DataTypeGeometryTestTrait;

    /**
     * @var GeometryPosition
     */
    protected $dataType;

    public function setUp(): void
    {
        parent::setUp();
        $this->dataType = new GeometryPosition();
    }

    public function testGetName(): void
    {
        $this->assertSame('geometry:position', $this->dataType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Geometric position', $this->dataType->getLabel());
    }

    /**
     * @dataProvider validPositionProvider
     */
    public function testIsValidWithValidPositions(string $value): void
    {
        $this->assertTrue($this->dataType->isValid(['@value' => $value]));
    }

    public function validPositionProvider(): array
    {
        return [
            'simple' => ['100,200'],
            'zero' => ['0,0'],
            'large' => ['9999,8888'],
            'with spaces' => [' 100 , 200 '],
            'single digit' => ['1,2'],
        ];
    }

    /**
     * @dataProvider invalidPositionProvider
     */
    public function testIsValidWithInvalidPositions($value, string $label = ''): void
    {
        $this->assertFalse(
            $this->dataType->isValid(['@value' => $value]),
            "Should be invalid: $label"
        );
    }

    public function invalidPositionProvider(): array
    {
        return [
            'negative x' => ['-100,200', 'negative x'],
            'negative y' => ['100,-200', 'negative y'],
            'float x' => ['100.5,200', 'float x'],
            'float y' => ['100,200.5', 'float y'],
            'empty string' => ['', 'empty string'],
            'no comma' => ['100 200', 'no separator'],
            'text' => ['abc,def', 'non-numeric'],
            'single value' => ['100', 'single value'],
            'plus sign' => ['+100,200', 'plus sign not allowed'],
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
            'simple' => ['100,200', 'POINT (100 -200)'],
            'zero y' => ['100,0', 'POINT (100 0)'],
            'zero both' => ['0,0', 'POINT (0 0)'],
            'invalid' => ['not position', null],
        ];
    }

    public function testGetGeometryPointNegatesY(): void
    {
        // Position uses top-left origin, so y is negated for geometry.
        $result = $this->dataType->getGeometryPoint('100,200');
        $this->assertSame('POINT (100 -200)', $result);
    }

    public function testGetGeometryPointZeroYNotNegated(): void
    {
        // When y is 0, it should remain 0 (not -0).
        $result = $this->dataType->getGeometryPoint('100,0');
        $this->assertSame('POINT (100 0)', $result);
    }

    public function testGetGeometryPointWithArray(): void
    {
        $result = $this->dataType->getGeometryPoint(['@value' => '100,200']);
        $this->assertSame('POINT (100 -200)', $result);
    }

    public function testHydrate(): void
    {
        $valueObject = ['@value' => ' 100 , 200 '];
        $value = $this->createMock(\Omeka\Entity\Value::class);
        $adapter = $this->createMock(\Omeka\Api\Adapter\AbstractEntityAdapter::class);

        $value->expects($this->once())
            ->method('setValue')
            ->with('100,200');
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

    public function testGetGeometryFromValueWithPosition(): void
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
