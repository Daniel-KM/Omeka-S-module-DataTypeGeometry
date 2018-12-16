<?php
namespace DataTypeGeometry\DataType;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType as BaseAbstractDataType;
use Omeka\Entity\Value;
use Zend\View\Renderer\PhpRenderer;

abstract class AbstractDataType extends BaseAbstractDataType implements DataTypeInterface
{
    use QueryGeometryTrait;

    public function getOptgroupLabel()
    {
        return 'Cartography'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/data-type-geometry.css', 'DataTypeGeometry'));
        $view->headScript()->appendFile($view->assetUrl('js/data-type-geometry.js', 'DataTypeGeometry'));
    }

    public function isValid(array $valueObject)
    {
        $result = $this->parseGeometry($valueObject['@value']);
        return !empty($result);
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter)
    {
        $string = strtoupper(str_replace('  ', ' ', (trim($valueObject['@value']))));
        $value->setValue($string);
        $value->setLang(null);
        $value->setUri(null);
        $value->setValueResource(null);
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        return (string) $value->value();
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
        $result['@value'] = $string;
        // Deprecated.
        // $result['@type'] = 'http://geovocab.org/geometry#asWKT';
        $result['@type'] = 'http://www.opengis.net/ont/geosparql#wktLiteral';
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
        $value = strtoupper($value);
        try {
            $geometry = new GeoWktParser($value);
            $geometry = $geometry->parse();
        } catch (\Exception $e) {
            return null;
        }
        return $geometry;
    }

    abstract function getEntityClass();

    abstract function getGeometryFromValue($value);
}
