<?php declare(strict_types=1);

namespace DataTypeGeometry\DataType;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;

trait QueryGeometryTrait
{
    /**
     * @var bool
     */
    protected $isMysqlRecent = null;

    /**
     * @var bool
     */
    protected $isPosgreSql = false;

    /**
     * Build query on geometry (coordinates, box, zone).
     *
     * The query should be normalized with the helper normalizeGeometryQuery().
     *
     * Only the first property is used, if set.
     * @see \DataTypeGeometry\View\Helper\NormalizeGeometryQuery
     *
     * @todo Manage another operator than within (intersect, outside…): use direct queries or a specialized database.
     *
     * Difference between mysql and postgresql:
     * - Point = ST_Point => use only with ST_GeomFromText
     * - MBRContains = ST_Contains => MBR is used only with a box.
     * - ST_Distance_Sphere = ST_DistanceSphere => specific alias
     * - No ST_SetSRID = ST_SRID = ST_SetSRID
     *
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildQuery(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $query): void
    {
        static $isMysqlRecent = null;
        static $defaultSrid = null;

        if (empty($query['geo'])) {
            return;
        }

        if (is_null($isMysqlRecent)) {
            $services = $adapter->getServiceLocator();
            $isMysqlRecent = $services->get('ViewHelperManager')->get('databaseVersion')->isDatabaseRecent();
            $defaultSrid = (int) $services->get('Omeka\Settings')->get('datatypegeometry_locate_srid', \DataTypeGeometry\DataType\Geography::DEFAULT_SRID);
        }
        $this->isMysqlRecent = $isMysqlRecent;

        $geos = $query['geo'];
        $first = reset($geos);
        $isSingle = !is_array($first) || !is_numeric(key($geos));
        if ($isSingle) {
            $geos = [$geos];
        }

        $isGeography = (isset($geos[0]['mode']) && $geos[0]['mode'] === 'geography')
            || isset($geos[0]['around']['latitude'])
            || isset($geos[0]['mapbox'])
            || isset($geos[0]['area']);

        $srid = $isGeography ? $defaultSrid : 0;

        $geometryAlias = $isGeography
            ? $this->joinGeography($adapter, $qb, $query)
            : $this->joinGeometry($adapter, $qb, $query);

        foreach ($geos as $geo) {
            if ($isGeography) {
                if (array_key_exists('around', $geo)) {
                    if (array_key_exists('latitude', $geo['around'])) {
                        $this->searchAround($adapter, $qb, $geo['around'], $srid, $geometryAlias);
                    }
                } elseif (array_key_exists('mapbox', $geo)) {
                    $this->searchMapBox($adapter, $qb, $geo['mapbox'], $srid, $geometryAlias);
                } elseif (array_key_exists('area', $geo)) {
                    $this->searchZone($adapter, $qb, $geo['area'], $srid, $geometryAlias);
                }
            } else {
                if (array_key_exists('around', $geo)) {
                    if (array_key_exists('x', $geo['around'])) {
                        $this->searchXy($adapter, $qb, $geo['around'], $srid, $geometryAlias);
                    }
                } elseif (array_key_exists('box', $geo)) {
                    $this->searchBox($adapter, $qb, $geo['box'], $srid, $geometryAlias);
                } elseif (array_key_exists('zone', $geo)) {
                    $this->searchZone($adapter, $qb, $geo['zone'], $srid, $geometryAlias);
                }
            }
        }
    }

    /**
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $around
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchXy(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $around, $srid, $geometryAlias): void
    {
        $point = $adapter->createNamedParameter(
            $qb,
            'POINT(' . preg_replace('~[^\d.-]~', '', $around['x']) . ' ' . preg_replace('~[^\d.-]~', '', $around['y']) . ')'
        );
        $srid = $adapter->createNamedParameter($qb, $srid);
        $qb->andWhere($qb->expr()->lte(
            // Note: 'ST_GeomFromText("Point(49 2)")' is not correct, so use single quote.
            "ST_Distance(ST_GeomFromText($point, $srid), $geometryAlias.value)",
            $adapter->createNamedParameter($qb, $around['radius'])
        ));
    }

    /**
     * Disabled by default: General mysql error
     * (3618): st_distance_sphere(POINT, POLYGON) has not been implemented for geographic spatial reference systems.
     *
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $around
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchAround(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $around, $srid, $geometryAlias): void
    {
        // With srid 4326 (Mercator), the radius should be in metre.
        $radiusMetre = $around['unit'] === 'km' ? $around['radius'] * 1000.0 : $around['radius'] * 1.0;

        $expr = $qb->expr();
        if ($this->isPosgreSql || $this->isMysqlRecent) {
            $stDistanceSphere = $this->isPosgreSql ? 'ST_DistanceSphere' : 'ST_Distance_Sphere';
            $point = $adapter->createNamedParameter(
                $qb,
                'POINT(' . preg_replace('~[^\d.-]~', '', $around['longitude']) . ' ' . preg_replace('~[^\d.-]~', '', $around['latitude']) . ')'
            );
            $srid = $adapter->createNamedParameter($qb, $srid);
            $qb
                ->andWhere($expr->lte(
                    "$stDistanceSphere(ST_GeomFromText($point, $srid), $geometryAlias.value)",
                    $adapter->createNamedParameter($qb, $radiusMetre)
                ));
        } else {
            $xLong = $adapter->createNamedParameter($qb, $around['longitude']);
            $yLat = $adapter->createNamedParameter($qb, $around['latitude']);
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
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $box
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchBox(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $box, $srid, $geometryAlias): void
    {
        $x1 = preg_replace('~[^\d.-]~', '', $box[0]);
        $y1 = preg_replace('~[^\d.-]~', '', $box[1]);
        $x2 = preg_replace('~[^\d.-]~', '', $box[2]);
        $y2 = preg_replace('~[^\d.-]~', '', $box[3]);
        $mbr = $adapter->createNamedParameter($qb, "Polygon(($x1 $y1, $x2 $y1, $x2 $y2, $x1 $y2, $x1 $y1))");
        $srid = $adapter->createNamedParameter($qb, $srid);

        $expr = $qb->expr();
        if ($this->isPosgreSql) {
            // Or use an enveloppe or a box.
            $qb->andWhere($expr->eq(
                "ST_Contains(ST_GeomFromText($mbr, $srid), $geometryAlias.value)",
                $expr->literal(true)
            ));
            return;
        }

        // "= true" is needed only to avoid an issue when converting dql to sql.
        $qb->andWhere($expr->eq(
            "MBRContains(ST_GeomFromText($mbr, $srid), $geometryAlias.value)",
            $expr->literal(true)
        ));
    }

    /**
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $mapbox
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchMapBox(AbstractEntityAdapter $adapter, QueryBuilder $qb, array $mapbox, $srid, $geometryAlias): void
    {
        $box = [$mapbox[1], $mapbox[0], $mapbox[3], $mapbox[2]];
        $this->searchBox($adapter, $qb, $box, $srid, $geometryAlias);
    }

    /**
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param string $wkt
     * @param int $srid
     * @param string|int|null $geometryAlias
     */
    protected function searchZone(AbstractEntityAdapter $adapter, QueryBuilder $qb, $wkt, $srid, $geometryAlias): void
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
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @return string Alias used for the geometry table.
     */
    protected function joinGeometry(AbstractEntityAdapter $adapter, QueryBuilder $qb, $query): string
    {
        return $this->joinGeo($adapter, $qb, $query, \DataTypeGeometry\Entity\DataTypeGeometry::class);
    }

    /**
     * Join the geography table.
     *
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @return string Alias used for the geography table.
     */
    protected function joinGeography(AbstractEntityAdapter $adapter, QueryBuilder $qb, $query): string
    {
        return $this->joinGeo($adapter, $qb, $query, \DataTypeGeometry\Entity\DataTypeGeography::class);
    }

    /**
     * Join the geography table.
     *
     * @param AbstractEntityAdapter $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @param string $dataTypeClass
     * @return string Alias used for the geography table.
     */
    protected function joinGeo(AbstractEntityAdapter $adapter, QueryBuilder $qb, $query, $dataTypeClass): string
    {
        $alias = $adapter->createAlias();
        $property = $query['geo'][0]['property'] ?? null;
        $expr = $qb->expr();
        if ($property) {
            $propertyId = $this->getPropertyId($adapter, $property);
            $qb->leftJoin(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->andX(
                    $expr->eq($alias . '.resource', 'omeka_root.id'),
                    $expr->eq($alias . '.property', $propertyId)
                )
            );
        } else {
            $qb->leftJoin(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $expr->eq($alias . '.resource', 'omeka_root.id')
            );
        }
        return $alias;
    }

    /**
     * Get a property id from a property term or an integer.
     *
     * @param AbstractEntityAdapter $adapter
     * @param string|int property
     * @return int
     */
    protected function getPropertyId(AbstractEntityAdapter $adapter, $property): int
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
        [$prefix, $localName] = explode(':', $property);
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
            ->setParameters(new ArrayCollection([
                new Parameter('localName', $localName, ParameterType::STRING),
                new Parameter('prefix', $prefix, ParameterType::STRING),
            ]))
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
    }
}
