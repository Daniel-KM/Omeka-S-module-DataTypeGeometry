<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geometry\Geometry as GeometryType;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface;
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
        $plugins = $view->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $escapeAttr = $plugins->get('escapeHtmlAttr');
        $formLabel = $plugins->get('formLabel');
        $formHidden = $plugins->get('formHidden');
        $formNumber = $plugins->get('formNumber');

        $validity = 'Value must be a valid integer position from the top left corner'; // @translate

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

        return '<div class="field-geometry">'
            . '<div class="error invalid-value" data-custom-validity="' . $escapeAttr($translate($validity)) . '"></div>'
            . $formHidden($hiddenValue)
            . '<div class="field-geometry-number">'
            . $formLabel($xElement)
            . $formNumber($xElement)
            . '</div>'
            . '<div class="field-geometry-number">'
            . $formLabel($yElement)
            . $formNumber($yElement)
            . '</div>'
            . '</div>'
        ;
    }

    public function isValid(array $valueObject)
    {
        return !empty($valueObject)
            && !empty($valueObject['@value'])
            && preg_match($this->regexPosition, (string) $valueObject['@value']);
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        // The value is already checked.
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
        return [
            '@value' => (string) $value->value(),
        ];
    }

    public function getGeometryPoint($value): ?string
    {
        $matches = [];
        $value = is_array($value) ? (string) $value['@value'] : (string) $value;
        return preg_match($this->regexPosition, (string) $value, $matches)
            ? 'POINT (' . $matches['x'] . ' ' . ($matches['y'] ? '-' : '') . $matches['y'] . ')'
            : null;
    }

    /**
     * Convert a string into a geometry representation.
     *
     * Warning:
     * unlike image postion, the geometry is bottom left based, so y is -y.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     *
     * @throws \InvalidArgumentException
     */
    public function getGeometryFromValue($value): GeometryInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometric position.'); // @translate
        }
        if (is_string($value)) {
            $matches = [];
            $value = preg_match($this->regexPosition, $value, $matches)
                ? 'POINT (' . $matches['x'] . ' ' . ($matches['y'] ? '-' : '') . $matches['y'] . ')'
                : strtoupper((string) $value);
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
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geometric position: %s', // @translate
                $value
            ));
        }
    }
}
