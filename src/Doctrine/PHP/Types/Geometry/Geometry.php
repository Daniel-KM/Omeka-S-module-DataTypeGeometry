<?php declare(strict_types=1);

namespace DataTypeGeometry\Doctrine\PHP\Types\Geometry;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use LongitudeOne\Spatial\DBAL\Types\GeometryType;
use LongitudeOne\Spatial\Exception as LongitudeOneException;
use LongitudeOne\Spatial\PHP\Types\AbstractGeometry;
use LongitudeOne\Spatial\PHP\Types\Geometry\LineString;
use LongitudeOne\Spatial\PHP\Types\Geometry\MultiLineString;
use LongitudeOne\Spatial\PHP\Types\Geometry\MultiPoint;
use LongitudeOne\Spatial\PHP\Types\Geometry\MultiPolygon;
use LongitudeOne\Spatial\PHP\Types\Geometry\Point;
use LongitudeOne\Spatial\PHP\Types\Geometry\Polygon;

/**
 * Generic geometry that can manage all geometries, individual or multiple.
 *
 * @todo Manage geometry collection. See the multipolygon class or neatline.
 * @see \Neatline\PHP\Types\Geometry\GeometryCollection
 */
class Geometry extends AbstractGeometry
{
    /**
     * The name cannot be the same than constant Geometry of GeometryInterface.
     *
     * @var AbstractGeometry
     */
    protected $geometryObject;

    /**
     * Check if a variable is a valid geometry.
     *
     * @param AbstractGeometry|array|string|null $geometry A geometry or a wkt.
     */
    public static function isValid($geometry): bool
    {
        return (new self)->validateGeometryValue($geometry) !== null;
    }

    public function getType(): string
    {
        return is_null($this->geometryObject)
            ? self::GEOMETRY
            : $this->geometryObject->getNamespace();
    }

    /**
     * @param AbstractGeometry|array|string|null $geometry A geometry or a wkt.
     *
     * @throws \LongitudeOne\Spatial\Exception\InvalidValueException
     */
    public function setGeometry($geometry): self
    {
        $this->geometryObject = $this->validateGeometryValue($geometry);
        if (empty($this->geometryObject)) {
            throw new LongitudeOneException\InvalidValueException('Invalid geometry.'); // @translate
        }
        return $this;
    }

    /**
     * @return GeometryType An object manageable by the database.
     *
     * @throws \LongitudeOne\Spatial\Exception\InvalidValueException
     */
    public function getGeometry(): ?AbstractGeometry
    {
        $this->isReady();
        return $this->geometryObject;
    }

    /**
     * @return array A representation of the values of the geometry as an array.
     *
     * @throws \LongitudeOne\Spatial\Exception\InvalidValueException
     */
    public function toArray(): array
    {
        $this->isReady();
        return $this->geometryObject->toArray();
    }

    /**
     * To GeoJSON Specification (RFC 7946).
     * @link https://tools.ietf.org/html/rfc7946
     *
     * @throws \LongitudeOne\Spatial\Exception\InvalidValueException
     *
     * {@inheritDoc}
     * @see \LongitudeOne\Spatial\PHP\Types\AbstractGeometry::toJson()
     */
    public function toJson()
    {
        // TODO Manage geometry collection.
        $this->isReady();
        $json = [];
        $json['type'] = $this->geometryObject->getType();
        $json['coordinates'] = $this->geometryObject->toArray();
        // The srid should be 0 for a flat geometry object.
        $json['srid'] = $this->geometryObject->getSrid();
        return json_encode($json);
    }

    /**
     * Convert a valid geometry into a database manageable geometry.
     *
     * @param AbstractGeometry|array|string|null $geometry A geometry or a wkt.
     */
    protected function validateGeometryValue($geometry): ?AbstractGeometry
    {
        if (is_object($geometry)) {
            return $geometry instanceof AbstractGeometry
                ? $geometry
                : null;
        } elseif (is_string($geometry)) {
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

        // The srid should be 0 for a flat geometry.
        $srid = empty($geometry['srid']) ? null : (int) $geometry['srid'];

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
            case 'MULTIPOINT':
            case self::MULTIPOINT:
                return new MultiPoint($coordinates, $srid);
            case 'MULTILINESTRING':
            case self::MULTILINESTRING:
                return new MultiLineString($coordinates, $srid);
            case 'MULTIPOLYGON':
            case self::MULTIPOLYGON:
                return new MultiPolygon($coordinates, $srid);
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
     *
     * @throws \LongitudeOne\Spatial\Exception\InvalidValueException
     */
    private function isReady(): bool
    {
        if (empty($this->geometryObject)) {
            throw new LongitudeOneException\InvalidValueException('Empty geometry.'); // @translate
        }
        return true;
    }

    /**
     * Must not call this: it means an empty geometry.
     */
    private function toStringGeometry(array $geometry): string
    {
        // Null is not allowed here, neither exception.
        return '';
    }
}
