<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\Form;

use DataTypeGeometry\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigForm();
        return $form
            ->setDatabaseVersion($services->get('ViewHelperManager')->get('databaseVersion'));
    }
}
