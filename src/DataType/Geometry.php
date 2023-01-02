<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geometry\Geometry as GenericGeometry;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;

/**
 * Geometry is a geography on a flat plane (srid = 0).
 */
class Geometry extends AbstractDataType
{
    public function getName()
    {
        return 'geometry';
    }

    public function getLabel()
    {
        return 'Geometry'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $element = new Element\Textarea('geometry');
        $element->setAttributes([
            'class' => 'value to-require geometry',
            'data-value-key' => '@value',
            // 'placeholder' => 'POINT (2.294497 48.858252)',
        ]);
        return $view->formTextarea($element);
    }

    public function getEntityClass(): string
    {
        return \DataTypeGeometry\Entity\DataTypeGeometry::class;
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     */
    public function getGeometryFromValue($value): \CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometry.'); // @translate
        }
        if (is_string($value)) {
            $value = strtoupper((string) $value);
        }
        try {
            return (new GenericGeometry($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geometry: %s', // @translate
                $value
            ));
        }
    }
}
