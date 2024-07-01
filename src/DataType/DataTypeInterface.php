<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;

interface DataTypeInterface
{
    /**
     * Get the fully qualified name of the corresponding entity.
     *
     * @return string
     */
    public function getEntityClass(): string;

    /**
     * Get the geometry to be stored from the passed value.
     *
     * Should throw \InvalidArgumentException if the passed value is invalid.
     *
     * @param string $value
     * @return \LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface|\LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface
     *
     * @throws \InvalidArgumentException
     */
    public function getGeometryFromValue($value);

    /**
     * Build a geometry query.
     *
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildQuery(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $query): void;
}
