<?php declare(strict_types=1);

namespace DataTypeGeometry;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

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
// $config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
// $entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');

if (version_compare($oldVersion, '3.0.1', '<')) {
    // This is a full reinstall.
    $this->install($services);

    $sql = <<<SQL
UPDATE value
SET type = "geometry:geometry"
WHERE type = "geometry";
SQL;
    $connection->executeStatement($sql);

    $messenger = new Messenger();
    $message = new Message(
        'You should reindex your geometries in the config of this module.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.1.6', '<')) {
    $sql = <<<SQL
DROP INDEX `idx_value` ON `data_type_geography`;
CREATE SPATIAL INDEX `idx_value` ON `data_type_geography` (`value`);
DROP INDEX `idx_value` ON `data_type_geometry`;
CREATE SPATIAL INDEX `idx_value` ON `data_type_geometry` (`value`);
SQL;
    $connection->executeStatement($sql);

    $settings->delete('datatypegeometry_buttons');

    $messenger = new Messenger();
    $message = new Message(
        'A new datatype has been added to manage geographic coordinates (latitude/longitude). It can manage be used as a source for the markers for the module Mapping too. A batch edit process is added to convert them.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'The data types of this module are no longer automatically appended to resource forms. They should be added to selected properties via a template.' // @translate
    );
    $messenger->addWarning($message);
}
