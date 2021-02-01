<?php declare(strict_types=1);
namespace DataTypeGeometry\DataType;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AdapterInterface;

interface DataTypeInterface
{
    /**
     * Get the fully qualified name of the corresponding entity.
     *
     * @return string
     */
    public function getEntityClass();

    /**
     * Get the geometry to be stored from the passed value.
     *
     * Should throw \InvalidArgumentException if the passed value is invalid.
     *
     * @throws \InvalidArgumentException
     * @param string $value
     * @return \CrEOF\Spatial\PHP\Types\Geography\GeographyInterface|\CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface|
     */
    public function getGeometryFromValue($value);

    /**
     * Build a geometry query.
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query);
}
