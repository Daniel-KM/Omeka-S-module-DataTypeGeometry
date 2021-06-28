<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType as BaseAbstractDataType;
use Omeka\Entity\Value;

abstract class AbstractDataType extends BaseAbstractDataType implements DataTypeInterface
{
    use QueryGeometryTrait;

    public function getOptgroupLabel()
    {
        return 'Geography / geometry'; // @translate
    }

    public function prepareForm(PhpRenderer $view): void
    {
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/data-type-geometry.css', 'DataTypeGeometry'));
        $view->headScript()
            ->appendFile($assetUrl('vendor/terraformer/terraformer-1.0.12.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser-1.1.0.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser-1.2.1.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/data-type-geometry.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer']);
    }

    public function isValid(array $valueObject)
    {
        return !empty($valueObject)
            && !empty($valueObject['@value'])
            && !empty($this->parseGeometry($valueObject['@value']));
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter): void
    {
        $string = strtoupper(str_replace('  ', ' ', (trim((string) $valueObject['@value']))));
        $value->setValue($string);
        $value->setLang(null);
        $value->setUri(null);
        $value->setValueResource(null);
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        return (string) $value->value();
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {
        return null;
    }

    /**
     * GeoJSON Specification (RFC 7946) is not used: it is not fully compliant
     * with json-ld.
     * @see https://github.com/json-ld/json-ld.org/issues/397
     * @link https://tools.ietf.org/html/rfc7946
     * @link http://www.opengeospatial.org/standards/geosparql
     *
     * {@inheritDoc}
     * @see \Omeka\DataType\DataTypeInterface::getJsonLd()
     */
    public function getJsonLd(ValueRepresentation $value)
    {
        // TODO Replace the srid by the uri and prepend it to the @value.
        // The default uri is http://www.opengis.net/def/crs/OGC/1.3/CRS84 (srid
        // 4326) according to the standard.
        // TODO Find a way to output a cleaned wkt from the value, without srid (without connection).
        $geometry = $this->getGeometryFromValue($value->value());
        $srid = $geometry->getSrid();
        $string = preg_replace('/\s+/', ' ', $value->value());
        if ($srid && stripos($string, 'srid') !== false) {
            $string = trim(substr($string, strpos(';') + 1));
        }
        $result = [];
        // Deprecated.
        // $result['@type'] = 'http://geovocab.org/geometry#asWKT';
        $result['@type'] = 'http://www.opengis.net/ont/geosparql#wktLiteral';
        $result['@value'] = $string;
        if ($srid) {
            $result['srid'] = $srid;
        }
        return $result;
    }

    /**
     * Convert a wkt string (with or without srid) into a geometry array.
     *
     * @param string $value
     * @return array|null
     */
    public function parseGeometry($value)
    {
        $value = strtoupper((string) $value);
        try {
            $geometry = new GeoWktParser($value);
            $geometry = $geometry->parse();
        } catch (\Exception $e) {
            return null;
        }
        return $geometry;
    }

    abstract public function getEntityClass();

    abstract public function getGeometryFromValue($value);
}
