<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geometry\Geometry as GenericGeometry;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Entity\Value;

/**
 * x and y may be integer or float.
 */
class GeometryCoordinates extends Geometry
{
    protected $regexCoordinates = '~^\s*(?<x>[+-]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+))\s*,\s*(?<y>[+-]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+))$~';

    public function getName()
    {
        return 'geometry:coordinates';
    }

    public function getLabel()
    {
        return 'Geometric coordinates'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $translate = $view->plugin('translate');
        $escapeAttr = $view->plugin('escapeHtmlAttr');
        $validity = 'Value must be valid coordinates (x and y)'; // @translate

        $hiddenValue = (new Element\Hidden('@value'))
            ->setAttributes([
                'class' => 'value to-require',
                'data-value-key' => '@value',
            ]);
        $xElement = (new Element\Number('x'))
            ->setLabel('x')
            ->setAttributes([
                'class' => 'geometry-coordinates geometry-coordinates-x',
                'step' => 'any',
            ]);
        $yElement = (new Element\Number('y'))
            ->setLabel('y')
            ->setAttributes([
                'class' => 'geometry-coordinates geometry-coordinates-y',
                'step' => 'any',
            ]);

        return '<div class="field-geometry">'
            . '<div class="error invalid-value" data-custom-validity="' . $escapeAttr($translate($validity)) . '"></div>'
            . $view->formHidden($hiddenValue)
            . '<div class="field-geometry-number">'
            . $view->formLabel($xElement)
            . $view->formNumber($xElement)
            . '</div>'
            . '<div class="field-geometry-number">'
            . $view->formLabel($yElement)
            . $view->formNumber($yElement)
            . '</div>'
            . '</div>'
        ;
    }

    public function isValid(array $valueObject)
    {
        return !empty($valueObject)
            && !empty($valueObject['@value'])
            && preg_match($this->regexCoordinates, (string) $valueObject['@value']);
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        // Remove the leading + if any. The value is already checked.
        $matches = [];
        preg_match($this->regexCoordinates, (string) $valueObject['@value'], $matches);
        $x = trim($matches['x'], '+ ');
        $y = trim($matches['y'], '+ ');
        $value->setValue($x . ',' . $y);
        $value->setLang(null);
        $value->setUri(null);
        $value->setValueResource(null);
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {
        return (string) $value->value();
    }

    public function getJsonLd(ValueRepresentation $value)
    {
        $matches = [];
        preg_match($this->regexCoordinates, (string) $value->value(), $matches);
        $x = $matches['x'];
        $y = $matches['y'];
        $result = [];
        $result['@value'] = [
            'x' => (float) $x,
            'y' => (float) $y,
        ];
        return $result;
    }

    public function getGeometryPoint($value): ?string
    {
        $matches = [];
        $value = is_array($value) && isset($value['x']) && isset($value['y'])
            ? $value['x'] . ',' . $value['y']
            : (string) $value;
        return preg_match($this->regexCoordinates, (string) $value, $matches)
           ? 'POINT (' . $matches['x'] . ' ' . $matches['y'] . ')'
            : null;
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     * @return \CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface
     */
    public function getGeometryFromValue($value): \CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometric coordinates.'); // @translate
        }
        if (is_string($value)) {
            $matches = [];
            $value = preg_match($this->regexCoordinates, (string) $value, $matches)
                ? 'POINT (' . $matches['x'] . ' ' . $matches['y'] . ')'
                : strtoupper((string) $value);
        }
        try {
            return (new GenericGeometry($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geometric coordinates: %s', // @translate
                $value
            ));
        }
    }
}
