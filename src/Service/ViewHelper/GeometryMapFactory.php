<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\ViewHelper;

use DataTypeGeometry\View\Helper\GeometryMap;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class GeometryMapFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // The whole "datatypegeometry" key, so a site's local.config.php can
        // override the layers and the map defaults in one place.
        $config = $services->get('Config');
        return new GeometryMap(
            $config['datatypegeometry'] ?? []
        );
    }
}
