<?php
namespace DataTypeGeometry\View\Helper;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use Doctrine\ORM\EntityManager;
use Zend\View\Helper\AbstractHelper;

class NormalizeGeometryQuery extends AbstractHelper
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Normalize a query for geometries (around a point, box and zone).
     *
     * The arguments are exclusive, so multiple args are removed to keep only
     * the first good one (point, then box, then zone).
     * When strings are used, the separator can be anything except ".".
     * The regular format is the first one.
     * The input can be single a single query or a list of query.
     * It can be used for front-end form or back-end api.
     *
     * Common for geometry and geography:
     * [geo][property] = 'dcterms:spatial' or another one (term or id)
     *
     * Geometry (for flat image or projected map):
     * [geo][around][x] = x
     * [geo][around][y] = y
     * [geo][around][radius] = radius
     * [geo][box] = [left x, top y, right x, bottom y] or [[left, top], [right, bottom]] or string "x1 y1 x2 y2"
     * [geo][zone] = "wkt"
     *
     * Geographic (for georeferenced data, require a non-empty srid):
     * [geo][around][latitude] = latitude (y)
     * [geo][around][longitude] = longitude (x)
     * [geo][around][radius] = radius
     * [geo][around][unit] = 'km' (default) or 'm' (1 km = 1000 m)
     * [geo][mapbox] = [top lat, left long, bottom lat, right lat] or [[top, left], [bottom, right]] or string "lat1 long1 lat2 long2"
     * [geo][box] = see above (can be used too when the srid is set).
     * [geo][zone] = "wkt"
     * [geo][srid] = srid (Spatial Reference Identifier, required for mapbox, box
     * or zone; generally 4326 because the default map is a Mercator projection).
     * @see http://spatialreference.org/ref/epsg/wgs-84/ or https://epsg.io/4326
     *
     * @param array $query
     * @return array The cleaned query.
     */
    public function __invoke($query)
    {
        if (empty($query['geo'])) {
            return $query;
        }

        $isSingle = !is_numeric(key($query['geo']));
        if ($isSingle) {
            $query['geo'] = [$query['geo']];
        }

        $defaults = [
            // Property (data type should be "geometry"), or search all properties.
            'property' => null,
            'around' => [
                // Geometric coordinates as x and y.
                'x' => null,
                'y' => null,
                // Geographic coordinates as latitude and longitude.
                'latitude' => null,
                'longitude' => null,
                // A float.
                'radius' => null,
                // Unit only for geographic radius. Can be km 'default) or m.
                // For a flat image, it is the unit of it (pixel).
                'unit' => null,
            ],
            // Two opposite coordinates (xy).
            'box' => null,
            // Two opposite geographic coordinates (latitude and longitued).
            'mapbox' => null,
            // A well-known text for geometry or geography (with srid).
            'zone' => null,
            // The spatial reference identifier to use.
            'srid' => null,
        ];

        foreach ($query['geo'] as $key => &$geo) {
            $result = [];
            $geo += $defaults;

            $property = $this->getPropertyId($geo['property']);
            if (is_int($property)) {
                $result['property'] = $property;
            }

            $srid = $this->normalizeSrid($geo['srid']);
            if ($srid) {
                $result['srid'] = $srid;
            }

            $geo['around'] = array_filter($geo['around'], function ($v) {
                return $v !== null && $v !== '';
            });
            if ($geo['around']) {
                $around = $this->normalizeAround($geo['around'] + $defaults['around']);
                if ($around) {
                    $geo = $result;
                    $geo['around'] = $around;
                    continue;
                }
            }

            if ($geo['box']) {
                $box = $this->normalizeBox($geo['box']);
                if ($box) {
                    $geo = $result;
                    $geo['box'] = $box;
                    continue;
                }
            }

            if ($geo['mapbox']) {
                $mapbox = $this->normalizeMapBox($geo['mapbox']);
                if ($mapbox) {
                    $geo = $result;
                    $geo['mapbox'] = $mapbox;
                    continue;
                }
            }

            if ($geo['zone']) {
                $zone = $this->normalizeZone($geo['zone']);
                if ($zone) {
                    $geo = $result;
                    $geo ['zone'] = $zone;
                    continue;
                }
            }

            unset($query['geo'][$key]);
        }
        unset($geo);

        if (empty($query['geo'])) {
            unset($query['geo']);
        } elseif ($isSingle) {
            $query['geo'] = reset($query['geo']);
        }
        return $query;
    }

    protected function normalizeAround($around)
    {
        $isGeography = isset($around['latitude']);
        if ($isGeography) {
            if (!isset($around['longitude'])
                || empty($around['radius'])
            ) {
                return;
            }

            if (!is_numeric($around['latitude'])
                || !is_numeric($around['longitude'])
                || !is_numeric($around['radius'])
            ) {
                return;
            }

            if ($around['latitude'] < -90
                || $around['latitude'] > 90
                || $around['longitude'] < -180
                || $around['longitude'] > 180
                || $around['radius'] <= 0
            ) {
                return;
            }

            $around['unit'] = isset($around['unit']) && in_array($around['unit'], ['km', 'm'])
                ? $around['unit']
                : 'km';
            switch ($around['unit']) {
                case 'km':
                    if ($around['radius'] > 20038) {
                        return;
                    }
                    break;
                case 'm':
                default:
                    if ($around['radius'] > 20038000) {
                        return;
                    }
                    break;
            }

            return array_intersect_key(
                $around,
                ['latitude' => null, 'longitude' => null, 'radius' => null, 'unit' => null]
            );
        }

        if (!isset($around['x'])
            || !isset($around['y'])
            || empty($around['radius'])
        ) {
            return;
        }

        if (!is_numeric($around['x'])
            || !is_numeric($around['y'])
            || !is_numeric($around['radius'])
        ) {
            return;
        }

        return array_intersect_key(
            $around,
            ['x' => null, 'y' => null, 'radius' => null]
        );
    }

    protected function normalizeBox($box)
    {
        if (is_array($box)) {
            if (count($box) === 4) {
                $left = $box[0];
                $top = $box[1];
                $right = $box[2];
                $bottom = $box[3];
            } elseif (count($box) !== 2 || count($box[0]) !== 2 || count($box[1]) !== 2) {
                return;
            } else {
                $left = $box[0][0];
                $top = $box[0][1];
                $right = $box[1][0];
                $bottom = $box[1][1];
            }
        } else {
            $box = preg_replace('[^0-9.]', ' ', $box);
            $box = preg_replace('/\s+/', ' ', trim($box));
            if (strpos($box, ' ') === false) {
                return;
            }
            list($left, $top, $right, $bottom) = explode(' ', $box, 4);
        }
        if (is_numeric($left)
            && is_numeric($top)
            && is_numeric($right)
            && is_numeric($bottom)
            && $left != $right
            && $top != $bottom
        ) {
            return [$left, $top, $right, $bottom];
        }
    }

    protected function normalizeMapBox($mapbox)
    {
        $mapbox = $this->normalizeBox($mapbox);
        if ($mapbox) {
            $top = $mapbox[0];
            $left = $mapbox[1];
            $bottom = $mapbox[2];
            $right = $mapbox[3];
            if ($top >= -90 && $top <= 90
                && $left >= -180 && $left <= 180
                && $bottom >= -90 && $bottom <= 90
                && $right >= -180 && $right <= 180
            ) {
                return $mapbox;
            }
        }
    }

    protected function normalizeZone($zone)
    {
        $zone = trim($zone);
        try {
            $geometry = new GeoWktParser($zone);
            $geometry = $geometry->parse();
        } catch (\Exception $e) {
            return;
        }
        return $zone;
    }

    protected function normalizeSrid($srid)
    {
        return (int) $srid;
    }

    /**
     * Get a property id from a property term or an integer.
     *
     * @param string|int property
     * @return int
     */
    protected function getPropertyId($property)
    {
        static $properties = [];

        if (is_null($property) || $property === '') {
            return;
        }

        if (isset($properties[$property])) {
            return $properties[$property];
        }

        if (empty($property)) {
            return 0;
        }

        if (is_numeric($property)) {
            $property = (int) $property;
            $properties[$property] = $property;
            return (int) $property;
        }

        if (!preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $property)) {
            $properties[$property] = 0;
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
        $properties[$property] = (int) $this->entityManager
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
        return $properties[$property];
    }
}
