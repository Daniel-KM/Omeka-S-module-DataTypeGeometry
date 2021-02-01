<?php
namespace DataTypeGeometry\Service\ViewHelper;

use DataTypeGeometry\View\Helper\GeometryFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GeometryFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = $services->get('FormElementManager')
            ->get(\DataTypeGeometry\Form\SearchFieldset::class);
        return new GeometryFieldset(
            $fieldset
        );
    }
}
