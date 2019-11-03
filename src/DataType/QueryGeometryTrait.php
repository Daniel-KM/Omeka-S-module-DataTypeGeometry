<?php
namespace DataTypeGeometry\DataType;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AdapterInterface;

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
     * The query should be normalized with the helper normalizeGeometryQuery().
     * Only the first property and the first srid are used, if set.
     * @see \DataTypeGeometry\View\Helper\NormalizeGeometryQuery
     *
     * @todo Manage another operator than within (intersect, outside…): use direct queries or a specialized database.
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
    public function buildQuery(AdapterInterface $adapter, QueryBuilder $qb, array $query)
    {
        if (empty($query['geo'])) {
            return;
        }

        $geos = $query['geo'];
        $first = reset($geos);
        $isSingle = !is_array($first) || !is_numeric(key($geos));
        if ($isSingle) {
            $geos = [$geos];
        }

        $srid = empty($geos[0]['srid']) ? 0 : (int) $geos[0]['srid'];
        $isGeography = $srid
            || isset($geos[0]['around']['latitude'])
            || isset($geos[0]['mapbox'])
            || isset($geos[0]['area']);
        $geometryAlias = $isGeography
            ? $this->joinGeography($adapter, $qb, $query)
            : $this->joinGeometry($adapter, $qb, $query);

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
                $this->searchZone($adapter, $qb, $geo['zone'], $srid, $geometryAlias);
            } elseif (array_key_exists('area', $geo)) {
                $this->searchZone($adapter, $qb, $geo['area'], $srid, $geometryAlias);
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
        $xLong = $adapter->createNamedParameter($qb, $around['x']);
        $yLat = $adapter->createNamedParameter($qb, $around['y']);
        $srid = $adapter->createNamedParameter($qb, $srid);
        $qb->andWhere($qb->expr()->lte(
            // Note: 'ST_GeomFromText("Point(49 2)")' is not correct.
            "ST_Distance(ST_GeomFromText('Point($xLong $yLat)', $srid), $geometryAlias.value)",
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
        // With srid 4326 (Mercator), the radius should be in metre.
        $radiusMetre = $around['unit'] === 'km' ? $around['radius'] * 1000 : $around['radius'];

        $xLong = $adapter->createNamedParameter($qb, $around['longitude']);
        $yLat = $adapter->createNamedParameter($qb, $around['latitude']);

        $expr = $qb->expr();
        if ($this->isPosgreSql) {
            $srid = $adapter->createNamedParameter($qb, $srid);
            $qb
                ->andWhere($expr->lte(
                    "ST_DistanceSphere(ST_GeomFromText('Point($xLong $yLat)', $srid), $geometryAlias.value)",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
        } elseif ($this->isMysql576) {
            $srid = $adapter->createNamedParameter($qb, $srid);
            $qb
                ->andWhere($expr->lte(
                    "ST_Distance_Sphere(ST_GeomFromText('Point($xLong $yLat)', $srid), $geometryAlias.value)",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
        } else {
            $radiusDegree = $radiusMetre / 111133;
            $radius = $adapter->createNamedParameter($qb, $radiusDegree);
            $qb
                ->andWhere($expr->eq(
                    "ST_Contains(ST_Buffer(Point($xLong, $yLat), $radius), $geometryAlias.value)",
                    $expr->literal(true)
                ));
            /*
            // Flat or small map.
            $qb->andWhere($expr->lte(
                "ST_Distance(Point($xLong, $yLat), $geometryAlias.value)",
                $adapter->createNamedParameter($qb, $radiusMetre)
            ));
            // Ok but only for points.
            // @see https://stackoverflow.com/questions/1973878/sql-search-using-haversine-in-doctrine#2102375
            $qb
                ->andWhere($expr->lte(
                    "(6370986 * ACOS(SIN(RADIANS($yLat)) * SIN(RADIANS(ST_Y($geometryAlias.value))) + COS(RADIANS($yLat)) * COS(RADIANS(ST_Y($geometryAlias.value))) * COS(RADIANS(ST_X($geometryAlias.value)) - RADIANS($xLong))))",
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
        $x1 = $adapter->createNamedParameter($qb, $box[0]);
        $y1 = $adapter->createNamedParameter($qb, $box[1]);
        $x2 = $adapter->createNamedParameter($qb, $box[2]);
        $y2 = $adapter->createNamedParameter($qb, $box[3]);
        $srid = $adapter->createNamedParameter($qb, $srid);
        $mbr = "Polygon(($x1 $y1, $x2 $y1, $x2 $y2, $x1 $y2, $x1 $y1))";

        $expr = $qb->expr();
        if ($this->isPosgreSql) {
            // Or use an enveloppe or a box.
            $qb->andWhere($expr->eq(
                "ST_Contains(ST_GeomFromText('$mbr', $srid), $geometryAlias.value)",
                $expr->literal(true)
            ));
            return;
        }

        // "= true" is needed only to avoid an issue when converting dql to sql.
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
        $box = [$mapbox[1], $mapbox[0], $mapbox[3], $mapbox[2]];
        $this->searchBox($adapter, $qb, $box, $srid, $geometryAlias);
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
        $geometry = $adapter->createNamedParameter($qb, $wkt);
        $srid = $adapter->createNamedParameter($qb, $srid);

        // "= true" is needed only to avoid an issue when converting dql to sql.
        $expr = $qb->expr();
        $qb->andWhere($expr->eq(
            "ST_Contains(ST_GeomFromText($geometry, $srid), $geometryAlias.value)",
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
        $dataTypeClass = \DataTypeGeometry\Entity\DataTypeGeometry::class;
        return $this->joinGeo($adapter, $qb, $query, $dataTypeClass);
    }

    /**
     * Join the geography table.
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @return string Alias used for the geography table.
     */
    protected function joinGeography(AdapterInterface $adapter, QueryBuilder $qb, $query)
    {
        $dataTypeClass = \DataTypeGeometry\Entity\DataTypeGeography::class;
        return $this->joinGeo($adapter, $qb, $query, $dataTypeClass);
    }

    /**
     * Join the geography table.
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $dataTypeClass
     * @return string Alias used for the geography table.
     */
    protected function joinGeo(AdapterInterface $adapter, QueryBuilder $qb, $query, $dataTypeClass)
    {
        $resourceClass = $adapter->getEntityClass();
        $alias = $adapter->createAlias();
        $property = isset($query['geo'][0]['property']) ? $query['geo'][0]['property'] : null;
        $expr = $qb->expr();
        if ($property) {
            $propertyId = $this->getPropertyId($adapter, $property);
            $qb->leftJoin(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->andX(
                    $expr->eq($alias . '.resource', $resourceClass . '.id'),
                    $expr->eq($alias . '.property', $propertyId)
                )
            );
        } else {
            $qb->leftJoin(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $expr->eq($alias . '.resource', $resourceClass . '.id')
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
