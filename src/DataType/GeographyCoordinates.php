<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use DataTypeGeometry\Doctrine\PHP\Types\Geography\Geography as GenericGeography;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Entity\Value;

/**
 * WKT uses "Point(x y)", so "Point(longitude latitude)".
 * The Geographic coordinates use "latitude,longitude", more common for end users.
 * They are all xsd:decimal values.
 *
 * @see https://opengeospatial.github.io/ogc-geosparql/geosparql11/spec.html#geo:kmlLiteral
 */
class GeographyCoordinates extends Geography
{
    protected $regexLatitudeLongitude = '~^\s*(?<latitude>[+-]?(?:[1-8]?\d(?:\.\d+)?|90(?:\.0+)?))\s*,\s*(?<longitude>[+-]?(?:180(?:\.0+)?|(?:(?:1[0-7]\d)|(?:[1-9]?\d))(?:\.\d+)?))\s*$~';

    public function getName()
    {
        return 'geography:coordinates';
    }

    public function getLabel()
    {
        return 'Geographic coordinates'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        $plugins = $view->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $escapeAttr = $plugins->get('escapeHtmlAttr');
        $formLabel = $plugins->get('formLabel');
        $formHidden = $plugins->get('formHidden');
        $formNumber = $plugins->get('formNumber');

        $validity = 'Value must be valid coordinates (latitude and longitude)'; // @translate

        $hiddenValue = (new Element\Hidden('@value'))
            ->setAttributes([
                'class' => 'value to-require',
                'data-value-key' => '@value',
            ]);
        $latitudeElement = (new Element\Number('latitude'))
            ->setLabel('Latitude')
            ->setAttributes([
                'class' => 'geography-coordinates geography-coordinates-latitude',
                'step' => 'any',
                'min' => '-90.0',
                'max' => '90.0',
            ]);
        $longitudeElement = (new Element\Number('longitude'))
            ->setLabel('Longitude')
            ->setAttributes([
                'class' => 'geography-coordinates geography-coordinates-longitude',
                'step' => 'any',
                'min' => '-180.0',
                'max' => '180.0',
            ]);

        return '<div class="field-geometry">'
            . '<div class="error invalid-value" data-custom-validity="' . $escapeAttr($translate($validity)) . '"></div>'
            . $formHidden($hiddenValue)
            . '<div class="field-geometry-number">'
            . $formLabel($latitudeElement)
            . $formNumber($latitudeElement)
            . '</div>'
            . '<div class="field-geometry-number">'
            . $formLabel($longitudeElement)
            . $formNumber($longitudeElement)
            . '</div>'
            . '</div>'
        ;
    }

    public function isValid(array $valueObject)
    {
        return !empty($valueObject)
            && !empty($valueObject['@value'])
            && preg_match($this->regexLatitudeLongitude, (string) $valueObject['@value']);
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        // Remove the leading + if any. The value is already checked.
        $matches = [];
        preg_match($this->regexLatitudeLongitude, (string) $valueObject['@value'], $matches);
        $latitude = trim($matches['latitude'], '+ ');
        $longitude = trim($matches['longitude'], '+ ');
        $value->setValue($latitude . ',' . $longitude);
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
            '@type' => 'http://www.opengis.net/ont/geosparql#kmlLiteral',
        ];
    }

    public function getGeometryPoint($value): ?string
    {
        $matches = [];
        $value = is_array($value) ? (string) $value['@value'] : (string) $value;
        return preg_match($this->regexLatitudeLongitude, $value, $matches)
            ? 'POINT (' . $matches['longitude'] . ' ' . $matches['latitude'] . ')'
            : null;
    }

    /**
     * Convert a string into a geography representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     * @return \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
     */
    public function getGeometryFromValue($value): \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geographic coordinates.'); // @translate
        }
        if (is_string($value)) {
            $matches = [];
            $value = preg_match($this->regexLatitudeLongitude, $value, $matches)
                ? 'POINT (' . $matches['longitude'] . ' ' . $matches['latitude'] . ')'
                : strtoupper($value);
        } elseif (is_array($value) && isset($value['@value'])) {
            $value = (string) $value['@value'];
        } elseif (is_object($value) && $value instanceof ValueRepresentation) {
            $value = (string) $value->value();
        }
        try {
            return (new GenericGeography($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid geographic coordinates: %s', // @translate
                $value
            ));
        }
    }
}
