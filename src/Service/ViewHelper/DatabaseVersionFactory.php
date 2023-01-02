<?php declare(strict_types=1);

namespace DataTypeGeometry\Service\ViewHelper;

use DataTypeGeometry\View\Helper\DatabaseVersion;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DatabaseVersionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $version = $connection->executeQuery('SELECT VERSION();')->fetchOne();
        return new DatabaseVersion([
            'db' => stripos($version, 'mariadb') !== false ? 'mariadb' : 'mysql',
            'version' => strtok($version, '-'),
            'version_full' => $version,
        ]);
    }
}
