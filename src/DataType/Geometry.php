<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geometry\Geometry as GeometryType;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface;
use Omeka\Api\Representation\ValueRepresentation;

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
        $plugins = $view->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $escapeAttr = $plugins->get('escapeHtmlAttr');

        $validity = 'Please enter a valid wkt for the geometry.'; // @translate

        $element = new Element\Textarea('geometry');
        $element->setAttributes([
            'class' => 'value to-require geometry',
            'data-value-key' => '@value',
            'data-invalid-message' => $validity,
            // 'placeholder' => 'POINT (2.294497 48.858252)',
        ]);

        return '<div class="error invalid-value" data-custom-validity="' . $escapeAttr($translate($validity)) . '"></div>'
            . $view->formTextarea($element)
            # BCT: opens the map editor for this value. Ctrl+Alt+M does the same
            # from inside the field; the button is what makes it discoverable.
            . '<button type="button" class="geometry-map-open o-icon-map-select" title="'
            . $escapeAttr($translate('Select on map')) . '">' // @translate
            . $escapeAttr($translate('Select on map')) . '</button>';
    }

    public function getEntityClass(): string
    {
        return \DataTypeGeometry\Entity\DataTypeGeometry::class;
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        # BCT: render the wkt geometry as a Leaflet map, from this module's own
        # assets. See src/View/Helper/GeometryMap.php.
        return $view->geometryMap((string) $value->value());
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     *
     * @throws \InvalidArgumentException
     */
    public function getGeometryFromValue($value): GeometryInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometry.'); // @translate
        }
        if (is_string($value)) {
            $value = strtoupper((string) $value);
        } elseif (is_array($value) && isset($value['@value'])) {
            $value = (string) $value['@value'];
        } elseif (is_object($value) && $value instanceof ValueRepresentation) {
            $value = (string) $value->value();
        }
        try {
            $geo = new GeometryType();
            return $geo
                ->setGeometry($value)
                ->getGeometry();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geometry: %s', // @translate
                $value
            ));
        }
    }
}
