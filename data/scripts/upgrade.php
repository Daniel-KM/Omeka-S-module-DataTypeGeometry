<?php declare(strict_types=1);

namespace DataTypeGeometry;

use Common\Stdlib\PsrMessage;

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
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.60')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.60'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.0.1', '<')) {
    // This is a full reinstall.
    $this->install($services);

    $sql = <<<SQL
UPDATE value
SET type = "geometry:geometry"
WHERE type = "geometry";
SQL;
    $connection->executeStatement($sql);

    $message = new PsrMessage(
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

    $message = new PsrMessage(
        'A new datatype has been added to manage geographic coordinates (latitude/longitude). It can manage be used as a source for the markers for the module Mapping too. A batch edit process is added to convert them.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
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
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        $message = new PsrMessage(
            'Your database is not compatible with geographic search: only flat geometry is supported.' // @translate
        );
        $messenger->addWarning($message);
    }

    $message = new PsrMessage(
        'Datatype names were simplified: "geometry", "geography", "geography:coordinates".' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'Two new datatypes have been added to manage geometries: x/y coordinates ("geometry:coordinates") and position from top left ("geometry:position").' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.4', '<')) {
    $message = new PsrMessage(
        'WARNING: the value representation has been normalized in the api to follow the opengis specifications for geography:coordinates, geometry:coordinates and geometry:position. The rdf value is now always a string, no more an array. Check compatibility with your external tools if needed.' // @translate
    );
    $messenger->addWarning($message);
}
