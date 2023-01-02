<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geometry\Geometry as GenericGeometry;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Entity\Value;

/**
 * Geometry position is not coordinates: the base (0,0) is the top left corner.
 */
class GeometryPosition extends Geometry
{
    protected $regexPosition = '~^\s*(?<x>\d+)\s*,\s*(?<y>\d+)\s*$~';

    public function getName()
    {
        return 'geometry:position';
    }

    public function getLabel()
    {
        return 'Geometric position'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $translate = $view->plugin('translate');
        $escape = $view->plugin('escapeHtml');

        $hiddenValue = (new Element\Hidden('@value'))
            ->setAttributes([
                'class' => 'value to-require',
                'data-value-key' => '@value',
            ]);
        $xElement = (new Element\Number('x'))
            ->setLabel('x')
            ->setAttributes([
                'class' => 'geometry-position geometry-position-x',
                'step' => 'any',
                'min' => '0',
            ]);
        $yElement = (new Element\Number('y'))
            ->setLabel('y')
            ->setAttributes([
                'class' => 'geometry-position geometry-position-y',
                'step' => 'any',
                'min' => '0',
            ]);

        $strings = [];
        $strings['validity'] = $escape($translate('Value must be a valid integer position from the top left corner')); // @translate
        $div = <<<HTML
<div class="error invalid-value" data-custom-validity="{$strings['validity']}"></div>
HTML;
        return $div
            . $view->formHidden($hiddenValue)
            . $view->formLabel($xElement)
            . $view->formNumber($xElement)
            . $view->formLabel($yElement)
            . $view->formNumber($yElement);
    }

    public function isValid(array $valueObject)
    {
        return !empty($valueObject)
            && !empty($valueObject['@value'])
            && preg_match($this->regexPosition, (string) $valueObject['@value']);
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        // Remove the leading + if any. The value is already checked.
        $matches = [];
        preg_match($this->regexPosition, (string) $valueObject['@value'], $matches);
        $x = $matches['x'];
        $y = $matches['y'];
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
        preg_match($this->regexPosition, (string) $value->value(), $matches);
        $x = $matches['x'];
        $y = $matches['y'];
        $result = [];
        $result['@value'] = [
            'x' => (int) $x,
            'y' => (int) $y,
        ];
        return $result;
    }

    public function getGeometryPoint($value): ?string
    {
        $matches = [];
        $value = is_array($value) && isset($value['x']) && isset($value['y'])
            ? $value['x'] . ',' . ($value['y'] ? '-' : '') . $value['y']
            : (string) $value;
        return preg_match($this->regexPosition, (string) $value, $matches)
            ? 'POINT (' . $matches['x'] . ' ' . ($matches['y'] ? '-' : '') . $matches['y'] . ')'
            : null;
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @todo Check if y should be from bottom left.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     * @return \CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface
     */
    public function getGeometryFromValue($value): \CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometric position.'); // @translate
        }
        if (is_string($value)) {
            $matches = [];
            $value = preg_match($this->regexPosition, (string) $value, $matches)
                ? 'POINT (' . $matches['x'] . ' ' . ($matches['y'] ? '-' : ''). $matches['y'] . ')'
                : strtoupper((string) $value);
        }
        try {
            return (new GenericGeometry($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geometric position: %s', // @translate
                $value
            ));
        }
    }
}
