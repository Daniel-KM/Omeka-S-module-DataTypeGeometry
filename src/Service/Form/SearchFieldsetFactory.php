<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\Form;

use DataTypeGeometry\Form\SearchFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = new SearchFieldset(null, $options ?? []);
        return $fieldset
            ->setDatabaseVersion($services->get('ViewHelperManager')->get('databaseVersion'));
    }
}
