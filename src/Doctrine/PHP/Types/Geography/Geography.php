<?php

namespace DataTypeGeometry\Doctrine\PHP\Types\Geography;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use CrEOF\Spatial\DBAL\Types\GeographyType;
use CrEOF\Spatial\PHP\Types\AbstractGeometry;
use CrEOF\Spatial\PHP\Types\Geography\GeographyInterface;
use CrEOF\Spatial\PHP\Types\Geography\LineString;
use CrEOF\Spatial\PHP\Types\Geography\Point;
use CrEOF\Spatial\PHP\Types\Geography\Polygon;

/**
 * Generic geography that can manage all geometries, individual or multiple.
 *
 * @todo Manage geometry collection. See the multipolygon class or neatline.
 * @see \Neatline\PHP\Types\Geometry\GeometryCollection
 */
class Geography extends AbstractGeometry implements GeographyInterface
{
    /**
     * @var AbstractGeometry
     * The name cannot be the same than constant Geometry of GeometryInterface.
     */
    protected $geometryObject;

    /**
     * @param AbstractGeometry|array|string|null A geometry, or a wkt.
     * @throws \CrEOF\Spatial\Exception\InvalidValueException
     * @param int|null $srid
     */
    public function __construct($geometry = null, $srid = null)
    {
        if ($geometry) {
            $this
                ->setGeometry($geometry)
                ->setSrid($srid);
        }
    }

    /**
     * Check if a variable is a valid geometry.
     *
     * @param AbstractGeometry|array|string $geometry
     * @return bool
     */
    public static function isValid($geometry)
    {
        return (new self)->validateGeometryValue($geometry) !== null;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return is_null($this->geometryObject)
            ? self::GEOGRAPHY
            : $this->geometryObject->getNamespace();
    }

    /**
     * @param AbstractGeometry|array|string $geometry
     * @throws \CrEOF\Spatial\Exception\InvalidValueException
     * @return self
     */
    public function setGeometry($geometry)
    {
        $this->geometryObject = $this->validateGeometryValue($geometry);
        if (empty($this->geometryObject)) {
            throw new \CrEOF\Spatial\Exception\InvalidValueException('Invalid geometry.'); // @translate
        }
        return $this;
    }

    /**
     * @return GeographyType An object manageable by the database.
     */
    public function getGeometry()
    {
        $this->isReady();
        return $this->geometryObject;
    }

    /**
     * @return array A representation of the values of the geometry as an array.
     */
    public function toArray()
    {
        $this->isReady();
        return $this->geometryObject->toArray();
    }

    /**
     * To GeoJSON Specification (RFC 7946).
     * @link https://tools.ietf.org/html/rfc7946
     *
     * {@inheritDoc}
     * @see \CrEOF\Spatial\PHP\Types\AbstractGeometry::toJson()
     */
    public function toJson()
    {
        // TODO Manage geometry collection.
        $this->isReady();
        $geo = [];
        $geo['type'] = $this->geometryObject->getType();
        $geo['coordinates'] = $this->geometryObject->toArray();
        $geo['srid'] = $this->geometryObject->getSrid();
        return json_encode($geo);
    }

    /**
     * Convert a valid geometry into a database manageable geometry.
     *
     * @param AbstractGeometry|array|string $geometry
     * @return AbstractGeometry|null
     */
    protected function validateGeometryValue($geometry)
    {
        if (is_object($geometry)) {
            return $geometry instanceof AbstractGeometry
                ? $geometry
                : null;
        }
        if (is_string($geometry)) {
            try {
                $geometry = new GeoWktParser($geometry);
                $geometry = $geometry->parse();
                $type = $geometry['type'];
                $coordinates = $geometry['value'];
            } catch (\Exception $e) {
                return null;
            }
        } elseif (is_array($geometry)) {
            // Manage geojson.
            if (array_key_exists('geometry', $geometry)) {
                $type = $geometry['geometry']['type'];
                $coordinates = $geometry['geometry']['coordinates'];
            }
            // Manage Doctrine / gis format.
            elseif (array_key_exists('type', $geometry) && array_key_exists('value', $geometry)) {
                $type = $geometry['type'];
                $coordinates = $geometry['value'];
            } else {
                return null;
            }
        } else {
            return null;
        }
        $srid = empty($geometry['srid']) ? null : $geometry['srid'];

        switch ($type) {
            case 'POINT':
            case self::POINT:
                return new Point($coordinates, $srid);
            case 'LINESTRING':
            case self::LINESTRING:
                return new LineString($coordinates, $srid);
            case 'POLYGON':
            case self::POLYGON:
                return new Polygon($coordinates, $srid);
            // TODO Create geometry multicollection. See the multipolygon class.
            // case 'GEOMETRYCOLLECTION':
            // case self::GEOMETRYCOLLECTION:
            //     return new GeometryColllection($coordinates, $srid);
            default:
                return null;
        }
    }

    /**
     * Check if the geometry is ready (not null, so not the type "Geometry").
     */
    private function isReady()
    {
        if (empty($this->geometryObject)) {
            throw new \CrEOF\Spatial\Exception\InvalidValueException('Empty geometry.'); // @translate
        }
    }

    /**
     * Must not call this: it means an empty geometry.
     *
     * @param array $geometry
     * @return string
     */
    private function toStringGeography(array $geometry)
    {
        // Null is not allowed here, neither exception.
        return '';
    }
}
