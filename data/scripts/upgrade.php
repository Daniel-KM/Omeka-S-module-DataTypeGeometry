<?php declare(strict_types=1);

namespace DataTypeGeometry;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.0.1', '<')) {
    // This is a full reinstall.
    $this->install($services);

    $sql = <<<SQL
UPDATE value
SET type = "geometry:geometry"
WHERE type = "geometry";
SQL;
    $connection->executeStatement($sql);

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

    $message = new Message(
        'A new datatype has been added to manage geographic coordinates (latitude/longitude). It can manage be used as a source for the markers for the module Mapping too. A batch edit process is added to convert them.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'The data types of this module are no longer automatically appended to resource forms. They should be added to selected properties via a template.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.2-beta', '<')) {
    $sqls = <<<'SQL'
UPDATE value
SET type = "geography"
WHERE type = "geometry:geography";
UPDATE value
SET type = "geometry"
WHERE type = "geometry:geometry";
UPDATE value
SET type = "geography:coordinates"
WHERE type = "geometry:geography:coordinates";
UPDATE value
SET type = "geometry:coordinates"
WHERE type = "geometry:geometry:coordinates";
UPDATE value
SET type = "geometry:position"
WHERE type = "geometry:geometry:position";

ALTER TABLE `data_type_geography` CHANGE `value` `value` GEOMETRY NOT NULL COMMENT '(DC2Type:geography)';
ALTER TABLE `data_type_geometry` CHANGE `value` `value` GEOMETRY NOT NULL COMMENT '(DC2Type:geometry)';
SQL;
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        $connection->executeStatement($sql);
    }

    // \DataTypeGeometry\DataType\Geography::DEFAULT_SRID
    $defaultSrid = (int) $settings->get('datatypegeometry_locate_srid', 4326);
    $sql = <<<SQL
UPDATE `data_type_geography`
SET `value` = ST_SRID(`value`, $defaultSrid)
WHERE ST_SRID(`value`) != $defaultSrid;
SQL;
    $connection->executeStatement($sql);

    $message = new Message(
        'Datatype names were simplified: "geometry", "geography", "geography:coordinates".' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'Two new datatypes have been added to manage geometries: x/y coordinates ("geometry:coordinates") and position from top left ("geometry:position").' // @translate
    );
    $messenger->addSuccess($message);
}
