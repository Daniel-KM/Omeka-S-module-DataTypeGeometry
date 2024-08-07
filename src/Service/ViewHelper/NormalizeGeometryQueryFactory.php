<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\ViewHelper;

use DataTypeGeometry\View\Helper\NormalizeGeometryQuery;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class NormalizeGeometryQueryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new NormalizeGeometryQuery(
            $services->get('EasyMeta'),
            $services->get('Omeka\EntityManager')
        );
    }
}
