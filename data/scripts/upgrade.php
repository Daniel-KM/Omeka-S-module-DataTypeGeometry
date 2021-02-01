<?php declare(strict_types=1);
namespace DataTypeGeometry;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.1', '<')) {
    // This is a full reinstall.
    $this->install($services);

    $sql = <<<SQL
UPDATE value
SET type = "geometry:geometry"
WHERE type = "geometry";
SQL;
    $connection->exec($sql);

    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
    $messenger->addWarning('You should reindex your geometries in the config of this module.'); // @translate
}
