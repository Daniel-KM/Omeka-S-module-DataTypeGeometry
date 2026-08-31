<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\FacetedBrowse\FacetType;

use DataTypeGeometry\FacetedBrowse\FacetType\WithinBox;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class WithinBoxFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new WithinBox($services->get('FormElementManager'));
    }
}
