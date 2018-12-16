<?php
namespace DataTypeGeometry\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AdapterInterface;

/**
 * This trait must be used inside an adapter, because there are calls to the
 * adapter methods.
 * Nevertheless, the second method is used in Module too.
 */
trait QueryGeometryTrait
{
    /**
     * @var bool
     */
    protected $isMysql576 = false;

    /**
     * @var bool
     */
    protected $isPosgreSql = false;

    /**
     * Build query on geometry (coordinates, box, zone).
     *
     * Only the first property and the first srid are used, if set.
     *
     * @todo Manage another operator than within (intersect, outside…): use direct queries or a specialized database.
     *
     * @see \DataTypeGeometry\View\Helper\NormalizeGeometryQuery for the format.
     *
     * Difference between mysql and postgresql:
     * - Point = ST_Point => use only with ST_GeomFromText
     * - MBRContains = ST_Contains => MBR is used only with a box.
     * - ST_Distance_Sphere = ST_DistanceSphere => specific alias
     * - No ST_SetSRID
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function searchGeometry(AdapterInterface $adapter, QueryBuilder $qb, array $query)
    {
        $normalizeGeometryQuery = $adapter->getServiceLocator()->get('ViewHelperManager')->get('normalizeGeometryQuery');
        $query = $normalizeGeometryQuery($query);
        if (empty($query['geo'])) {
            return;
        }
        $geos = $query['geo'];
        $first = reset($geos);
        $isSingle = !is_array($first) || !is_numeric(key($geos));
        if ($isSingle) {
            $geos = [$geos];
        }
        $geometryAlias = $this->joinGeometry($adapter, $qb, $query);
        $srid = empty($geos[0]['srid']) ? 0 : (int) $geos[0]['srid'];

        foreach ($geos as $geo) {
            if (array_key_exists('around', $geo)) {
                array_key_exists('latitude', $geo['around'])
                    ? $this->searchAround($adapter, $qb, $geo['around'], $srid, $geometryAlias)
                    : $this->searchXy($adapter, $qb, $geo['around'], $srid, $geometryAlias);
            } elseif (array_key_exists('box', $geo)) {
                $this->searchBox($adapter, $qb, $geo['box'], $srid, $geometryAlias);
            } elseif (array_key_exists('mapbox', $geo)) {
                $this->searchMapBox($adapter, $qb, $geo['mapbox'], $srid, $geometryAlias);
            } elseif (array_key_exists('zone', $geo)) {
                $this->searchWkt($adapter, $qb, $geo['zone'], $srid, $geometryAlias);
            }
        }
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $around
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchXy(AdapterInterface $adapter, QueryBuilder $qb, array $around, $srid, $geometryAlias)
    {
        // Note: 'ST_GeomFromText("Point(2 50)")' is not correct.
        $point = sprintf("ST_GeomFromText('Point(%s %s)', %d)", $around['x'], $around['y'], $srid);
        $qb->andWhere($qb->expr()->lte(
            "ST_Distance($point, $geometryAlias.value)",
            $adapter->createNamedParameter($qb, $around['radius'])
        ));
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $around
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchAround(AdapterInterface $adapter, QueryBuilder $qb, array $around, $srid, $geometryAlias)
    {
        $latitude = $around['latitude'];
        $longitude = $around['longitude'];
        $radius = $around['radius'];
        $unit = $around['unit'];

        // With srid 4326 (Mercator), the radius should be in metre.
        $radiusMetre = $unit === 'km' ? $radius * 1000 : $radius;

        $expr = $qb->expr();
        if ($this->isPosgreSql) {
            $point = sprintf("ST_GeomFromText('Point(%s %s)', %d)", $around['longitude'], $around['latitude'], $srid);
            $qb
                ->andWhere($expr->lte(
                    "ST_DistanceSphere($point, $geometryAlias.value)",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
        } elseif ($this->isMysql576) {
            $point = sprintf("ST_GeomFromText('Point(%s %s)', %d)", $around['longitude'], $around['latitude'], $srid);
            $qb
                ->andWhere($expr->lte(
                    "ST_Distance_Sphere($point, $geometryAlias.value)",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
        } else {
            $radiusDegree = $radiusMetre / 111133;
            $buffer = sprintf("ST_Buffer(Point(%s, %s), %s)", $longitude, $latitude, $radiusDegree);
            $qb
                ->andWhere($expr->eq(
                    "ST_Contains($buffer, $geometryAlias.value)",
                    $expr->literal(true)
                ));
            /*
            // Flat or small map.
            $qb->andWhere($expr->lte(
                "ST_Distance($point, $geometryAlias.value)",
                $adapter->createNamedParameter($qb, $radiusMetre)
            ));
            // Ok but only for points.
            // @see https://stackoverflow.com/questions/1973878/sql-search-using-haversine-in-doctrine#2102375
            $qb
                ->andWhere($expr->lte(
                    "(6370986 * ACOS(SIN(RADIANS($around['latitude'])) * SIN(RADIANS(ST_Y($geometryAlias.value))) + COS(RADIANS($around['latitude'])) * COS(RADIANS(ST_Y($geometryAlias.value))) * COS(RADIANS(ST_X($geometryAlias.value)) - RADIANS($around['longitude']))))",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
            */
        }
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $box
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchBox(AdapterInterface $adapter, QueryBuilder $qb, array $box, $srid, $geometryAlias)
    {
        $polygon = 'Polygon((%1$s %2$s, %3$s %2$s, %3$s %4$s, %1$s %4$s, %1$s %2$s))';
        $mbr = vsprintf($polygon, $box);

        if ($this->isPosgreSql) {
            // Or use an enveloppe or a box.
            return $this->searchZone($adapter, $qb, $mbr, $srid, $geometryAlias);
        }

        // "= true" is needed only to avoid an issue when converting dql to sql.
        $expr = $qb->expr();
        $qb->andWhere($expr->eq(
            "MBRContains(ST_GeomFromText('$mbr', $srid), $geometryAlias.value)",
            $expr->literal(true)
        ));
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $mapbox
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchMapBox(AdapterInterface $adapter, QueryBuilder $qb, array $mapbox, $srid, $geometryAlias)
    {
        $polygon = 'Polygon((%2$s %1$s, %2$s %3$s, %4$s %3$s, %4$s %1$s, %2$s %1$s))';
        $mbr = vsprintf($polygon, $mapbox);

        if ($this->isPosgreSql) {
            // Or use an enveloppe or a box.
            return $this->searchZone($adapter, $qb, $mbr, $srid, $geometryAlias);
        }

        // "= true" is needed only to avoid an issue when converting dql to sql.
        $expr = $qb->expr();
        $qb->andWhere($expr->eq(
            "MBRContains(ST_GeomFromText('$mbr', $srid), $geometryAlias.value)",
            $expr->literal(true)
        ));
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param string $wkt
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchZone(AdapterInterface $adapter, QueryBuilder $qb, $wkt, $srid, $geometryAlias)
    {
        // "= true" is needed only to avoid an issue when converting dql to sql.
        $geometry = "ST_GeomFromText('$wkt', $srid)";
        $expr = $qb->expr();
        $qb->andWhere($expr->eq(
            "ST_Contains($geometry, $geometryAlias.value)",
            $expr->literal(true)
        ));
    }

    /**
     * Join the geometry table.
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @return string Alias used for the geometry table.
     */
    protected function joinGeometry(AdapterInterface $adapter, QueryBuilder $qb, $query)
    {
        $resourceClass = $adapter->getEntityClass();
        $dataTypeClass = \DataTypeGeometry\Entity\DataTypeGeometry::class;
        $alias = $adapter->createAlias();
        $property = isset($query['geo'][0]['property']) ? $query['geo'][0]['property'] : null;
        if ($property) {
            $propertyId = $this->getPropertyId($adapter, $property);
            $expr = $qb->expr();
            $qb->join(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->andX(
                    $expr->eq($alias . '.resource', $resourceClass . '.id'),
                    $expr->eq($alias . '.property', $propertyId)
                )
            );
        } else {
            $qb->join(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->eq($alias . '.resource', $resourceClass . '.id')
            );
        }
        return $alias;
    }

    /**
     * Get a property id from a property term or an integer.
     *
     * @param AdapterInterface $adapter
     * @param string|int property
     * @return int
     */
    protected function getPropertyId(AdapterInterface $adapter, $property)
    {
        if (empty($property)) {
            return 0;
        }
        if (is_numeric($property)) {
            return (int) $property;
        }
        if (!preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $property)) {
            return 0;
        }
        list($prefix, $localName) = explode(':', $property);
        $dql = <<<'DQL'
SELECT p.id
FROM Omeka\Entity\Property p
JOIN p.vocabulary v
WHERE p.localName = :localName
AND v.prefix = :prefix
DQL;
        return (int) $adapter
            ->getEntityManager()
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
    }
}
