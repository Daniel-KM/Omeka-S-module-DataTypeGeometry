<?php declare(strict_types=1);

namespace DataTypeGeometry;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use DataTypeGeometry\Form\BatchEditFieldset;
use DataTypeGeometry\Form\ConfigForm;
use DataTypeGeometry\Form\SearchFieldset;
use DataTypeGeometry\Job\IndexGeometries;
use Doctrine\Common\Collections\Criteria;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Stdlib\Message;

/**
 * Data type Geometry
 *
 * Adds a data type Geometry to properties of resources and allows to manage
 * values in Omeka or an an external database.
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        // Load composer dependencies. No need to use init().
        require_once __DIR__ . '/vendor/autoload.php';

        // TODO It is possible to register each geometry separately (line, point…). Is it useful? Or a Omeka type is enough (geometry:point…)? Or a column in the table (no)?
        \Doctrine\DBAL\Types\Type::addType(
            'geometry:geometry',
            \CrEOF\Spatial\DBAL\Types\GeometryType::class
        );
        \Doctrine\DBAL\Types\Type::addType(
            'geometry:geography',
            \CrEOF\Spatial\DBAL\Types\GeographyType::class
        );
    }

    public function install(ServiceLocatorInterface $serviceLocator): void
    {
        $this->setServiceLocator($serviceLocator);

        // In case of upgrade of a recent version of Cartography, the database
        // may exist.
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = 'SHOW tables LIKE "data_type_geometry";';
        $stmt = $connection->query($sql);
        $table = $stmt->fetchColumn();
        if ($table) {
            $this->execSqlFromFile($this->modulePath() . '/data/install/uninstall-cartography.sql');
        }

        if (!$this->supportSpatialSearch()) {
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
            $messenger->addWarning(sprintf('Your database does not support advanced spatial search. See the minimum requirements in readme.')); // @translate
        }

        $useMyIsam = $this->requireMyIsamToSupportGeometry();
        if ($useMyIsam) {
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
            $messenger->addWarning(sprintf('Your database does not support modern spatial indexing. It has no impact in common cases. See the minimum requirements in readme.')); // @translate
        }

        $filepath = $useMyIsam
            ? $this->modulePath() . '/data/install/schema-myisam.sql'
            :  $this->modulePath() . '/data/install/schema.sql';
        $this->execSqlFromFile($filepath);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            \Annotate\Controller\Admin\AnnotationController::class,
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
            \Annotate\Controller\Site\AnnotationController::class,
        ];
        foreach ($controllers as $controller) {
            // Display and filter the search for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );
        }

        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            \Annotate\Api\Adapter\AnnotationBodyHydrator::class,
            \Annotate\Api\Adapter\AnnotationTargetHydrator::class,
        ];
        foreach ($adapters as $adapter) {
            // Search resources and annotations by geometries.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'searchQuery']
            );

            // Save geometries in the external table.
            $sharedEventManager->attach(
                $adapter,
                'api.hydrate.post',
                [$this, 'saveGeometryData']
            );
        }

        $sharedEventManager->attach(
            \Annotate\Form\QuickSearchForm::class,
            'form.add_elements',
            [$this, 'addFormElementsAnnotateQuickSearch']
        );

        $sharedEventManager->attach(
            '*',
            'view.batch_edit.before',
            [$this, 'viewBatchEditBefore']
        );
        $sharedEventManager->attach(
            \Omeka\Form\ResourceBatchUpdateForm::class,
            'form.add_elements',
            [$this, 'formAddElementsResourceBatchUpdateForm']
        );
        $sharedEventManager->attach(
            \Omeka\Form\ResourceBatchUpdateForm::class,
            'form.add_input_filters',
            [$this, 'formAddInputFiltersResourceBatchUpdateForm']
        );
        // TODO The conversion to coordinates can be done for other resources.
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.preprocess_batch_update',
            [$this, 'batchUpdatePreprocess']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.batch_update.post',
            [$this, 'batchUpdatePost']
        );
    }

    public function getConfigForm(PhpRenderer $view)
    {
        $html = parent::getConfigForm($view);
        return '<p>'
            . $view->translate('Use "Batch edit items" to convert coordinates to/from mapping markers (require module Mapping).') // @translate
            . '</p>'
            . '<p>'
            . $view->translate('The jobs below are useless without module Cartography.') // @translate
            . '<br/>'
            . $view->translate('Reindex geometries or resources and annotations as geometry or geography.') // @translate
            . '</p>' . $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        // Save the srid.
        parent::handleConfigForm($controller);

        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        if (empty($params['process']) || $params['process'] !== $controller->translate('Process')) {
            $message = 'No job launched.'; // @translate
            $controller->messenger()->addWarning($message);
            return;
        }

        unset($params['csrf']);
        unset($params['process']);

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(IndexGeometries::class, $params);
        $message = new Message(
            'Processing in the background (%sjob #%d%s)', // @translate
            sprintf('<a href="%s">',
                htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>'
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
    }

    public function addFormElementsAnnotateQuickSearch(Event $event): void
    {
        $services = $this->getServiceLocator();
        $fieldset = $services->get('FormElementManager')->get(SearchFieldset::class);
        $form = $event->getTarget();
        $form->add($fieldset);
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function searchQuery(Event $event): void
    {
        $query = $event->getParam('request')->getContent();
        if (empty($query['geo'])) {
            return;
        }
        $normalizeGeometryQuery = $this->getServiceLocator()
            ->get('ViewHelperManager')->get('normalizeGeometryQuery');
        $query = $normalizeGeometryQuery($query);
        if (empty($query['geo'])) {
            return;
        }
        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $dataTypes = $this->getGeometryDataTypes();
        $dataTypes['geometry:geography']->buildQuery($adapter, $qb, $query);
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event): void
    {
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/data-type-geometry.css', 'DataTypeGeometry'));
        $view->headScript()
            ->appendFile($assetUrl('vendor/terraformer/terraformer-1.0.12.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser-1.1.0.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser-1.2.1.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/data-type-geometry.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer']);

        // There is no advanced search form, only a list of partials.
        $partials = $event->getParam('partials', []);
        $partials[] = 'common/advanced-search/data-type-geography';
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
    {
        $view = $event->getTarget();
        $query = $event->getParam('query', []);

        $normalizeGeometryQuery = $view->plugin('normalizeGeometryQuery');
        $query = $normalizeGeometryQuery($query);
        $event->setParam('query', $query);
        if (empty($query['geo'])) {
            return;
        }

        $filters = $event->getParam('filters');
        $translate = $event->getTarget()->plugin('translate');
        $geo = $query['geo'];
        if (!empty($geo['around'])) {
            $filterLabel = $translate('Geographic coordinates'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within %1$s %2$s of point %3$s %4$s'), // @translate
                $geo['around']['radius'], $geo['around']['unit'], $geo['around']['latitude'], $geo['around']['longitude']
            );
        } elseif (!empty($geo['around']['xy'])) {
            $filterLabel = $translate('Geometric coordinates'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within %1$s pixels of point x: %2$s, y: %3$s)'), // @translate
                $geo['around']['radius'], $geo['around']['x'], $geo['around']['y']
            );
        } elseif (!empty($geo['mapbox'])) {
            $filterLabel = $translate('Map box'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within box %1$s,%2$s/%3$s,%4$s'), // @translate
                $geo['mapbox'][0], $geo['mapbox'][1], $geo['mapbox'][2], $geo['mapbox'][3]
            );
        } elseif (!empty($geo['box'])) {
            $filterLabel = $translate('Box'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within box %1$s,%2$s/%3$s,%4$s'), // @translate
                $geo['box'][0], $geo['box'][1], $geo['box'][2], $geo['box'][3]
            );
        } elseif (!empty($geo['area'])) {
            $filterLabel = $translate('Within area'); // @translate
            $filters[$filterLabel][] = $geo['area'];
        } elseif (!empty($geo['zone'])) {
            $filterLabel = $translate('Within zone'); // @translate
            $filters[$filterLabel][] = $geo['zone'];
        }

        $event->setParam('filters', $filters);
    }

    public function viewBatchEditBefore(Event $event): void
    {
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/data-type-geometry.css', 'DataTypeGeometry'));
        $view->headScript()
            ->appendFile($assetUrl('js/data-type-geometry.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer']);
    }

    public function formAddElementsResourceBatchUpdateForm(Event $event): void
    {
        if (!$this->isModuleActive('Mapping')) {
            return;
        }

        /** @var \Omeka\Form\ResourceBatchUpdateForm $form */
        $form = $event->getTarget();
        $fieldset = $this->getServiceLocator()->get('FormElementManager')
            ->get(BatchEditFieldset::class);
        $form->add($fieldset);
    }

    public function formAddInputFiltersResourceBatchUpdateForm(Event $event): void
    {
        if (!$this->isModuleActive('Mapping')) {
            return;
        }

        /** @var \Laminas\InputFilter\InputFilterInterface $inputFilter */
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('geometry')
            ->add([
                'name' => 'manage_coordinates_markers',
                'required' => false,
            ])
            ->add([
                'name' => 'from_properties',
                'required' => false,
            ])
            ->add([
                'name' => 'to_property',
                'required' => false,
            ]);
    }

    public function batchUpdatePreprocess(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $post = $request->getContent();
        $data = $event->getParam('data');

        if (empty($post['geometry']['convert_literal_to_coordinates'])
            && empty($post['geometry']['manage_coordinates_markers'])
        ) {
            $data['geometry'] = null;
        } else {
            $manage = $post['geometry']['manage_coordinates_markers'];
            if (!in_array($manage, ['sync', 'coordinates_to_markers', 'markers_to_coordinates'])) {
                return;
            }
            if (empty($post['geometry']['from_properties']) || $post['geometry']['from_properties'] === 'all') {
                $from = null;
            } else {
                $from = $this->getPropertyIds($post['geometry']['from_properties']);
                if (!$from) {
                    return;
                }
            }
            if (empty($post['geometry']['to_property'])) {
                $to = null;
            } else {
                $to = $this->getPropertyId($post['geometry']['to_property']);
                if (!$to) {
                    return;
                }
            }
            if (in_array($manage, ['sync', 'markers_to_coordinates']) && !$to) {
                return;
            }
            $data['geometry'] = $post['geometry'];
            $data['geometry']['convert_literal_to_coordinates'] = !empty($data['geometry']['convert_literal_to_coordinates']);
            $data['geometry']['from_properties_ids'] = $from;
            $data['geometry']['to_property_id'] = $to;

            $data['geometry']['srid'] = $this->getServiceLocator()->get('Omeka\Settings')
                ->get('datatypegeometry_locate_srid', 4326);
        }
        $event->setParam('data', $data);
    }

    public function batchUpdatePost(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $data = $request->getContent();
        if (empty($data['geometry'])
            || (empty($data['geometry']['convert_literal_to_coordinates'])
                && empty($data['geometry']['manage_coordinates_markers'])
            )
        ) {
            return;
        }

        $ids = (array) $request->getIds();
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }

        if (!array_key_exists('srid', $data['geometry'])) {
            $data['geometry']['convert_literal_to_coordinates'] = !empty($data['geometry']['convert_literal_to_coordinates']);
            $data['geometry']['from_properties_ids'] = $this->getPropertyIds($data['geometry']['from_properties']);
            $data['geometry']['to_property_id'] = $this->getPropertyId($data['geometry']['to_property']);
            $data['geometry']['srid'] = $this->getServiceLocator()->get('Omeka\Settings')
                ->get('datatypegeometry_locate_srid', 4326);
        }

        if (!empty($data['geometry']['convert_literal_to_coordinates'])) {
            $this->convertLiteralToCoordinates($ids, $data);
        }

        if (!$this->isModuleActive('Mapping')) {
            return;
        }

        // TODO Use the adapter to update values/mapping markers.
        // $adapter = $event->getTarget();

        $manage = $data['geometry']['manage_coordinates_markers'];
        switch ($manage) {
            default:
                return;

            case 'sync':
                $this->copyCoordinatesToMarkers($ids, $data);
                $this->copyMarkersToCoordinates($ids, $data);
                break;

            case 'coordinates_to_markers':
                $this->copyCoordinatesToMarkers($ids, $data);
                break;

            case 'markers_to_coordinates':
                $this->copyMarkersToCoordinates($ids, $data);
                break;
        }
    }

    protected function convertLiteralToCoordinates(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $srid = $data['geometry']['srid'] ?? 4326;

        $bind = ['resource_ids' => $ids];
        $types = ['resource_ids' => $connection::PARAM_INT_ARRAY];

        $from = $data['geometry']['from_properties_ids'] ?? null;
        if ($from) {
            $sqlWhere = 'AND `value`.`property_id` IN (:property_ids)';
            $bind['property_ids'] = array_map('intval', $from);
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        } else {
            $sqlWhere = '';
        }

        // The single quote simplifies escaping of regex.
        $whereSql = <<<'SQL'
WHERE
    `value`.`resource_id` IN (:resource_ids)
    AND `value`.`type` = "literal"
    AND `value`.`value` REGEXP '^\\s*(?<latitude>[+-]?(?:[1-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?))\\s*,\\s*(?<longitude>[+-]?(?:180(?:\\.0+)?|(?:(?:1[0-7]\\d)|(?:[1-9]?\\d))(?:\\.\\d+)?))\\s*$'
SQL;
        $whereSql .= '    ' . $sqlWhere;

        // The update of the table `data_type_geometry` should be done first in
        // order to keep same results from the database.
        $sql = <<<SQL
INSERT INTO `data_type_geography`
    (`resource_id`, `property_id`, `value`)
SELECT DISTINCT
    `value`.`resource_id`,
    `value`.`property_id`,
    ST_GeomFromText(CONCAT(
        "POINT(",
        TRIM(SUBSTRING_INDEX(TRIM(`value`.`value`), ",", -1)),
        " ",
        TRIM(SUBSTRING_INDEX(TRIM(`value`.`value`), ",", 1)),
        ")"
    ), $srid)
FROM `value`
$whereSql
ON DUPLICATE KEY UPDATE
    `data_type_geography`.`id` = `data_type_geography`.`id`
;
SQL;
        $connection->executeUpdate($sql, $bind, $types);

$sql = <<<SQL
UPDATE `value`
SET
    `value`.`type` = "geometry:geography:coordinates"
$whereSql
SQL;
        $connection->executeUpdate($sql, $bind, $types);
    }

    protected function copyCoordinatesToMarkers(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $bind = ['resource_ids' => $ids];
        $types = ['resource_ids' => $connection::PARAM_INT_ARRAY];
        $from = $data['geometry']['from_properties_ids'] ?? null;
        if ($from) {
            $sqlWhere = 'AND `value`.`property_id` IN (:property_ids)';
            $bind['property_ids'] = array_map('intval', $from);
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        } else {
            $sqlWhere = '';
        }

        $sql = <<<SQL
INSERT INTO `mapping_marker`
    (`item_id`, `media_id`, `lat`, `lng`, `label`)
SELECT DISTINCT
    `value`.`resource_id`,
    NULL,
    SUBSTRING_INDEX(`value`.`value`, ",", 1),
    SUBSTRING_INDEX(`value`.`value`, ",", -1),
    NULL
FROM `value`
LEFT JOIN `mapping_marker`
    ON `mapping_marker`.`item_id` = `value`.`resource_id`
        AND CONCAT(`mapping_marker`.`lat`, ",", `mapping_marker`.`lng`) = `value`.`value`
WHERE
    `value`.`resource_id` IN (:resource_ids)
    AND `value`.`type` = "geometry:geography:coordinates"
    AND `mapping_marker`.`id` IS NULL
    $sqlWhere
;
SQL;
        $connection->executeUpdate($sql, $bind, $types);
    }

    protected function copyMarkersToCoordinates(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $srid = $data['geometry']['srid'] ?? 4326;

        $bind = [
            'resource_ids' => $ids,
            'property_id' => (int) $data['geometry']['to_property_id'],
        ];
        $types = ['resource_ids' => $connection::PARAM_INT_ARRAY];

        $fromSql = <<<SQL
FROM `mapping_marker`
LEFT JOIN `value`
    ON `value`.`resource_id` = `mapping_marker`.`item_id`
        AND `value`.`value` = CONCAT(`mapping_marker`.`lat`, ",", `mapping_marker`.`lng`)
        AND `value`.`type` = "geometry:geography:coordinates"
WHERE
    `mapping_marker`.`item_id` IN (:resource_ids)
    AND `value`.`id` IS NULL
;
SQL;

        // The update of the table `data_type_geometry` should be done first in
        // order to keep same results from the database.
        $sql = <<<SQL
INSERT INTO `data_type_geography`
    (`resource_id`, `property_id`, `value`)
SELECT DISTINCT
    `mapping_marker`.`item_id`,
    :property_id,
    ST_GeomFromText(CONCAT("POINT(", `mapping_marker`.`lng`, " ", `mapping_marker`.`lat`, ")"), $srid)
$fromSql
SQL;
        $connection->executeUpdate($sql, $bind, $types);

$sql = <<<SQL
INSERT INTO `value`
    (`resource_id`, `property_id`, `value_resource_id`, `type`, `lang`, `value`, `uri`, `is_public`)
SELECT DISTINCT
    `mapping_marker`.`item_id`,
    :property_id,
    NULL,
    "geometry:geography:coordinates",
    NULL,
    CONCAT(`mapping_marker`.`lat`, ",", `mapping_marker`.`lng`),
    NULL,
    1
$fromSql
SQL;
        $connection->executeUpdate($sql, $bind, $types);
    }

    /**
     * Save geometric data into the geometry table.
     *
     * This clears all existing geometries and (re)saves them during create and
     * update operations for a resource (item, item set, media). We do this as
     * an easy way to ensure that the geometries in the geometry table are in
     * sync with the geometries in the value table.
     *
     * @see \NumericDataTypes\Module::saveNumericData()
     *
     * @param Event $event
     */
    public function saveGeometryData(Event $event): void
    {
        $entity = $event->getParam('entity');
        if (!$entity instanceof \Omeka\Entity\Resource) {
            // This is not a resource.
            return;
        }

        $entityValues = $entity->getValues();

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $srid = $services->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', 4326);

        foreach ($this->getGeometryDataTypes() as $dataTypeName => $dataType) {
            // TODO Improve criteria when the types are mixed in a property.
            $criteria = Criteria::create()->where(Criteria::expr()->eq('type', $dataTypeName));
            $matchingValues = $entityValues->matching($criteria);
            // This resource has no data values of this type.
            if (!count($matchingValues)) {
                continue;
            }

            $dataTypeClass = $dataType->getEntityClass();

            // TODO Remove this persist, that is used only when a geometry is updated on the map.
            // Persist is required for annotation, since there is no cascade
            // persist between annotation and values.
            $entityManager->persist($entity);

            /** @var \DataTypeGeometry\Entity\DataTypeGeometry[] $existingDataValues */
            $existingDataValues = [];
            if ($entity->getId()) {
                $dql = sprintf('SELECT n FROM %s n WHERE n.resource = :resource', $dataTypeClass);
                $query = $entityManager->createQuery($dql);
                $query->setParameter('resource', $entity);
                $existingDataValues = $query->getResult();
            }

            foreach ($matchingValues as $value) {
                // Avoid ID churn by reusing data rows.
                $dataValue = current($existingDataValues);
                // No more number rows to reuse. Create a new one.
                if ($dataValue === false) {
                    $dataValue = new $dataTypeClass;
                    $entityManager->persist($dataValue);
                } else {
                    // Null out data values as we reuse them. Note that existing
                    // data values are already managed and will update during
                    // flush.
                    $existingDataValues[key($existingDataValues)] = null;
                    next($existingDataValues);
                }

                // Set the default srid when needed for geographic geometries.
                // TODO Add method setEntityValues.
                $geometry = $dataType->getGeometryFromValue($value->getValue());
                if ($srid
                    && in_array($dataTypeName, ['geometry:geography', 'geometry:geography:coordinates'])
                    && empty($geometry->getSrid())
                ) {
                    $geometry->setSrid($srid);
                }

                $dataValue->setResource($entity);
                $dataValue->setProperty($value->getProperty());
                $dataValue->setValue($geometry);
            }

            // Remove any data values that weren't reused.
            foreach ($existingDataValues as $existingDataValue) {
                if ($existingDataValue !== null) {
                    $entityManager->remove($existingDataValue);
                }
            }
        }
    }

    /**
     * Get all data types added by this module.
     *
     * Note: this is compliant with Omeka 1.2.
     *
     * @return \Omeka\DataType\AbstractDataType[]
     */
    public function getGeometryDataTypes()
    {
        $dataTypes = $this->getServiceLocator()->get('Omeka\DataTypeManager');
        return [
            'geometry:geography:coordinates' => $dataTypes->get('geometry:geography:coordinates'),
            'geometry:geography' => $dataTypes->get('geometry:geography'),
            'geometry:geometry' => $dataTypes->get('geometry:geometry'),
        ];
    }

    /**
     * Check if Omeka database has minimum requirements to search geometries.
     *
     * @see readme.md.
     *
     * @return bool
     */
    protected function supportSpatialSearch()
    {
        $db = $this->databaseVersion();
        switch ($db['db']) {
            case 'mysql':
                return version_compare($db['version'], '5.6.1', '>=');
            case 'mariadb':
                return version_compare($db['version'], '5.3.3', '>=');
            default:
                return true;
        }
    }

    /**
     * Check if the Omeka database requires myIsam to support Geometry.
     *
     * @see readme.md.
     *
     * @return bool Return false by default: if a specific database is used,
     * it is presumably geometry compliant.
     */
    protected function requireMyIsamToSupportGeometry()
    {
        $db = $this->databaseVersion();
        switch ($db['db']) {
            case 'mysql':
                return version_compare($db['version'], '5.7.5', '<');
            case 'mariadb':
                return version_compare($db['version'], '10.2.2', '<');
            case 'innodb':
                return version_compare($db['version'], '5.7.14', '<');
            default:
                return false;
        }
    }

    /**
     * Get  the version of the database.
     *
     * @return array with keys "db" and "version".
     */
    protected function databaseVersion()
    {
        $result = [
            'db' => '',
            'version' => '',
        ];

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $sql = 'SHOW VARIABLES LIKE "version";';
        $stmt = $connection->query($sql);
        $version = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);

        $isMySql = stripos($version, 'mysql') !== false;
        if ($isMySql) {
            $result['db'] = 'mysql';
            $result['version'] = $version;
            return $result;
        }

        $isMariaDb = stripos($version, 'mariadb') !== false;
        if ($isMariaDb) {
            $result['db'] = 'mariadb';
            $result['version'] = $version;
            return $result;
        }

        $sql = 'SHOW VARIABLES LIKE "innodb_version";';
        $stmt = $connection->query($sql);
        $version = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);
        $isInnoDb = !empty($version);
        if ($isInnoDb) {
            $result['db'] = 'innodb';
            $result['version'] = $version;
            return $result;
        }

        return $result;
    }

    /**
     * Get a property id by term.
     */
    protected function getPropertyId(?string $id): ?int
    {
        if (!$id) {
            return null;
        }
        $result = $this->getPropertyIds([$id]);
        return $result ? reset($result) : null;
    }

    /**
     * Get all property ids by term, or the specified ones.
     *
     * @return array Filtered associative array of ids by term.
     */
    protected function getPropertyIds(?array $ids = null): array
    {
        static $properties;

        if (is_null($properties)) {
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT property.id AS id',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'property.id',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_map('intval', array_column($properties, 'id', 'term'));
        }

        return $ids
            ? array_intersect_key($properties, array_flip($ids))
            : $properties;
    }
}
