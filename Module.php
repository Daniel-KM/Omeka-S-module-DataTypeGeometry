<?php declare(strict_types=1);

namespace DataTypeGeometry;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use DataTypeGeometry\DataType\Geography;
use DataTypeGeometry\Form\BatchEditFieldset;
use DataTypeGeometry\Form\ConfigForm;
use DataTypeGeometry\Form\SearchFieldset;
use DataTypeGeometry\Job\IndexGeometries;
use Doctrine\Common\Collections\Criteria;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface;
use Omeka\Module\AbstractModule;

/**
 * Data type Geometry
 *
 * Adds a data type Geometry to properties of resources and allows to manage
 * values in Omeka or an external database.
 *
 * @copyright Daniel Berthereau, 2018-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.60')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.60'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    /**
     * This method overrides TraitModule because the sql file depends on the
     * engine used for sql.
     */
    public function install(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);

        $this->initTranslations();
        $this->preInstall();

        // In case of upgrade of a recent version of Cartography, the database
        // may exist.
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $sql = 'SHOW tables LIKE "data_type_geometry";';
        $stmt = $connection->executeQuery($sql);
        $table = $stmt->fetchOne();
        if ($table) {
            $this->execSqlFromFile($this->modulePath() . '/data/install/uninstall-cartography.sql');
        }

        $databaseVersion = $this->getDatabaseVersion();
        $useMyIsam = $databaseVersion->requireMyIsamToSupportGeometry();

        $filepath = $useMyIsam
            ? $this->modulePath() . '/data/install/schema-myisam.sql'
            : $this->modulePath() . '/data/install/schema.sql';
        $this->execSqlFromFile($filepath);

        $this->postInstall();
    }

    protected function getDatabaseVersion(): \DataTypeGeometry\View\Helper\DatabaseVersion
    {
        $services = $this->getServiceLocator();

        // The module is not available during install.
        require_once __DIR__ . '/src/View/Helper/DatabaseVersion.php';
        require_once __DIR__ . '/src/Service/ViewHelper/DatabaseVersionFactory.php';

        /** @var \DataTypeGeometry\View\Helper\DatabaseVersion $databaseVersion */
        // $databaseVersion = $services->get('ViewHelperManager')->get('databaseVersion');
        $databaseVersion = new \DataTypeGeometry\Service\ViewHelper\DatabaseVersionFactory;

        return $databaseVersion($services, 'databaseVersion', []);
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $databaseVersion = $this->getDatabaseVersion();

        if (!$databaseVersion->supportGeographicSearch()) {
            $messenger->addWarning(
                'Your database does not support advanced spatial search. See the minimum requirements in readme.' // @translate
            );
        }

        $useMyIsam = $databaseVersion->requireMyIsamToSupportGeometry();
        if ($useMyIsam) {
            $messenger->addWarning(
                'Your database does not support modern spatial indexing. It has no impact in common cases. See the minimum requirements in readme.' // @translate
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Admin\Query',
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
                [$this, 'handleViewAdvancedSearch']
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
            \Omeka\Api\Adapter\ValueAnnotationAdapter::class,
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
            // TODO Fix spatial storage with srid.
            $sharedEventManager->attach(
                $adapter,
                'api.create.post',
                [$this, 'fixSridInDatabase']
            );
            $sharedEventManager->attach(
                $adapter,
                'api.update.post',
                [$this, 'fixSridInDatabase']
            );
        }

        $sharedEventManager->attach(
            \Annotate\Form\QuickSearchForm::class,
            'form.add_elements',
            [$this, 'addFormElementsAnnotateQuickSearch']
        );

        // Add the css/js to any resource form (there may be geographic data
        // without template).
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Annotation',
            'view.layout',
            [$this, 'addAdminResourceHeaders']
        );

        // Extend the batch edit form via js.
        $sharedEventManager->attach(
            '*',
            'view.batch_edit.before',
            [$this, 'addAdminResourceHeaders']
        );
        $sharedEventManager->attach(
            \Omeka\Form\ResourceBatchUpdateForm::class,
            'form.add_elements',
            [$this, 'formAddElementsResourceBatchUpdateForm']
        );

        // TODO The conversion to coordinates can be done for other resources but the module Mapping doesn't manage them.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.preprocess_batch_update',
            [$this, 'handleResourceBatchUpdatePreprocess']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.post',
            [$this, 'handleResourceBatchUpdatePost']
        );
    }

    public function getConfigForm(PhpRenderer $view)
    {
        $services = $this->getServiceLocator();

        $html = $this->getConfigFormAuto($view);

        /** @var \DataTypeGeometry\View\Helper\DatabaseVersion $databaseVersion */
        $databaseVersion = $services->get('ViewHelperManager')->get('databaseVersion');
        if (!$databaseVersion->supportGeographicSearch() || !$databaseVersion->isDatabaseRecent()) {
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning('Your database does not support full advanced spatial search. See the minimum requirements in readme.'); // @translate
        }

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
        $this->handleConfigFormAuto($controller);

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
        $message = new PsrMessage(
            'Processing in the background ({link}job #{job_id}{link_end})', // @translate
            [
                'link' => sprintf('<a href="%s">',
                    htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
            ]
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
        $dataTypes['geography']->buildQuery($adapter, $qb, $query);
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function handleViewAdvancedSearch(Event $event): void
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

        $this->handlePartialsAdvancedSearch($event);
    }

    public function handlePartialsAdvancedSearch(Event $event): void
    {
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
        if (isset($geo['around']['latitude']) && $geo['around']['latitude'] !== '') {
            $filterLabel = $translate('Geographic coordinates'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within %1$s %2$s of point %3$s %4$s'), // @translate
                $geo['around']['radius'], $geo['around']['unit'], $geo['around']['latitude'], $geo['around']['longitude']
            );
        } elseif (isset($geo['around']['x']) && $geo['around']['x'] !== '') {
            $filterLabel = $translate('Geometric coordinates'); // @translate
            $filters[$filterLabel][] = sprintf(
                $translate('Within %1$s pixels of point x: %2$s, y: %3$s'), // @translate
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

    public function addAdminResourceHeaders(Event $event): void
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
        /** @var \Omeka\Form\ResourceBatchUpdateForm $form */
        $form = $event->getTarget();
        $services = $this->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        // $resourceType = $form->getOption('resource_type');

        /** @var \DataTypeGeometry\Form\BatchEditFieldset $fieldset */
        $fieldset = $formElementManager->get(BatchEditFieldset::class);
        $form->add($fieldset);

        $groups = $form->getOption('element_groups');
        $groups['geometry'] = 'Geometry and geography'; // @translate
        $form->setOption('element_groups', $groups);

        if (!$this->isModuleActive('Mapping')) {
            $fieldset->remove('manage_coordinates_features');
            $fieldset->get('from_properties')->setLabel('Properties to convert from literal to geometric data'); // @translate
            $fieldset->remove('to_property');
        }
    }

    /**
     * Clean params for batch update.
     */
    public function handleResourceBatchUpdatePreprocess(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $post = $request->getContent();
        $data = $event->getParam('data');

        if (empty($post['geometry']) || !array_filter($post['geometry'])) {
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        if (empty($post['geometry']['convert_literal_to_coordinates'])
            && empty($post['geometry']['manage_coordinates_features'])
        ) {
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        if (!empty($post['geometry']['convert_literal_order'])
            && !in_array($post['geometry']['convert_literal_order'], ['latitude_longitude', 'longitude_latitude'])
        ) {
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        /**
         * @var \Laminas\Log\Logger $logger
         * @var \Common\Stdlib\EasyMeta $easyMeta
         * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $easyMeta = $services->get('EasyMeta');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        if (!empty($post['geometry']['convert_literal_to_coordinates'])
            && empty($post['geometry']['convert_literal_strict'])
            /** @see \DataTypeGeometry\View\Helper\DatabaseVersion::supportRegexpExt() */
            && !$services->get('ViewHelperManager')->get('databaseVersion')->supportRegexpExt()
        ) {
            $message = new PsrMessage('Your database does not support the function `regexp_substr`. Upgrade it to MariaDB 10.0.5 or MySQL 8.0.'); // @translate
            $logger->err($message->getMessage());
            $messenger->addError($message);
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        $manage = $post['geometry']['manage_coordinates_features'] ?? null;
        if ($manage && !in_array($manage, ['sync', 'coordinates_to_features', 'features_to_coordinates'])) {
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        if (empty($post['geometry']['from_properties'])) {
            $message = new PsrMessage('No source property set for conversion of geometric or geographic data.'); // @translate
            $logger->err($message->getMessage());
            $messenger->addError($message);
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        } elseif (!in_array('all', $post['geometry']['from_properties'])
            && !$easyMeta->propertyIds($post['geometry']['from_properties']
        )) {
            $message = new PsrMessage('Invalid source properties set for conversion of geometric or geographic data.'); // @translate
            $logger->err($message->getMessage());
            $messenger->addError($message);
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        if (in_array($manage, ['sync', 'features_to_coordinates'])
            && !empty($post['geometry']['to_property'])
        ) {
            $message = new PsrMessage('A destination property is needed to convert geometric or geographic data.'); // @translate
            $logger->err($message->getMessage());
            $messenger->addError($message);
            unset($data['geometry']);
            $event->setParam('data', $data);
            return;
        }

        if (!empty($post['geometry']['to_property'])) {
            $to = $easyMeta->propertyId($post['geometry']['to_property']);
            if (!$to) {
                $message = new PsrMessage('Invalid destination property set for conversion of geometric or geographic data.'); // @translate
                $logger->err($message->getMessage());
                $messenger->addError($message);
                unset($data['geometry']);
                $event->setParam('data', $data);
                return;
            }
        }

        $data['geometry'] = $post['geometry'];
        $data['geometry']['convert_literal_to_coordinates'] = !empty($data['geometry']['convert_literal_to_coordinates']);
        $data['geometry']['convert_literal_order'] = $data['geometry']['convert_literal_order'] ?? null;
        $data['geometry']['convert_literal_strict'] = !empty($data['geometry']['convert_literal_strict']);
        $data['geometry']['srid'] = $services->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', Geography::DEFAULT_SRID);

        $event->setParam('data', $data);
    }

    /**
     * Process action on batch update (all or partial) via direct sql.
     *
     * Data should be reindexed.
     *
     * @param Event $event
     */
    public function handleResourceBatchUpdatePost(Event $event): void
    {
        // TODO Event data is not available here, so use request content.
        // $data = $event->getParam('data');
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $data = $request->getContent();

        if (empty($data['geometry'])
            || !array_filter($data['geometry'])
            || (empty($data['geometry']['convert_literal_to_coordinates'])
                && empty($data['geometry']['manage_coordinates_features'])
            )
            || empty($data['geometry']['from_properties'])
        ) {
            return;
        }

        $ids = (array) $request->getIds();
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }

        if (!array_key_exists('srid', $data['geometry'])) {
            /** @var \Common\Stdlib\EasyMeta $easyMeta */
            $easyMeta = $this->getServiceLocator()->get('EasyMeta');
            $data['geometry']['convert_literal_to_coordinates'] = !empty($data['geometry']['convert_literal_to_coordinates']);
            $data['geometry']['convert_literal_order'] = $data['geometry']['convert_literal_order'] ?? null;
            $data['geometry']['convert_literal_strict'] = !empty($data['geometry']['convert_literal_strict']);
            $data['geometry']['from_properties_ids'] = empty($data['geometry']['from_properties']) || in_array('all', $data['geometry']['from_properties'])
                ? []
                : $easyMeta->propertyIds($data['geometry']['from_properties']);
            $data['geometry']['to_property_id'] = $easyMeta->propertyId($data['geometry']['to_property'] ?? null);
            $data['geometry']['srid'] = $this->getServiceLocator()->get('Omeka\Settings')
                ->get('datatypegeometry_locate_srid', Geography::DEFAULT_SRID);
        }

        if (!empty($data['geometry']['convert_literal_to_coordinates'])) {
            $this->convertLiteralToCoordinates($ids, $data);
        }

        if (!$this->isModuleActive('Mapping')) {
            return;
        }

        $isOldMapping = !$this->isModuleVersionAtLeast('Mapping', '2.0');

        // TODO Use the adapter to update values/mapping markers.
        // $adapter = $event->getTarget();

        $manage = $data['geometry']['manage_coordinates_features'] ?? null;

        if ($isOldMapping) {
            switch ($manage) {
                default:
                    return;

                case 'sync':
                    $this->copyCoordinatesToMarkers($ids, $data);
                    $this->copyMarkersToCoordinates($ids, $data);
                    break;

                case 'coordinates_to_markers':
                case 'coordinates_to_features':
                    $this->copyCoordinatesToMarkers($ids, $data);
                    break;

                case 'markers_to_coordinates':
                case 'features_to_coordinates':
                    $this->copyMarkersToCoordinates($ids, $data);
                    break;
            }
            return;
        }

        switch ($manage) {
            default:
                return;

            case 'sync':
                $this->copyCoordinatesToFeatures($ids, $data);
                $this->copyFeaturesToCoordinates($ids, $data);
                break;

            case 'coordinates_to_features':
                $this->copyCoordinatesToFeatures($ids, $data);
                break;

            case 'features_to_coordinates':
                $this->copyFeaturesToCoordinates($ids, $data);
                break;
        }
    }

    protected function convertLiteralToCoordinates(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $srid = $data['geometry']['srid'] ?? Geography::DEFAULT_SRID;

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

        // TODO Manage traditional coordinates NSEW with degrees.
        // TODO Manage geometry coordinates and position.

        // TODO Don't use (?:xxx|yyy) for compatibility with mysql 5.6.
        // The single quote simplifies escaping of regex. Use nowdoc to avoid
        // issues with backslashes.
        // Regex sql requires double backslashs, so check variables and nowdocs.
        $isLongLat = $data['geometry']['convert_literal_order'] === 'longitude_latitude';
        $isStrictLiteral = !empty($data['geometry']['convert_literal_strict']);
        if ($isLongLat && $isStrictLiteral) {
            $regexSql = <<<'REGEX_SQL'
^\\s*(?<longitude>[+-]?(?:180(?:\\.0+)?|(?:(?:1[0-7]\\d)|(?:[1-9]?\\d))(?:\\.\\d+)?))\\s*,\\s*(?<latitude>[+-]?(?:[1-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?))\\s*$
REGEX_SQL;
        } elseif ($isLongLat && !$isStrictLiteral) {
            $regexSql = <<<'REGEX_SQL'
^\\s*(?<longitude>[+-]?(?:180(?:\\.0+)?|(?:(?:1[0-7]\\d)|(?:[1-9]?\\d))(?:\\.\\d+)?))[^\\d.+-]+(?<latitude>[+-]?(?:[1-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?))\\s*$
REGEX_SQL;
        } elseif ($isStrictLiteral) {
            $regexSql = <<<'REGEX_SQL'
^\\s*(?<latitude>[+-]?(?:[1-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?))\\s*,\\s*(?<longitude>[+-]?(?:180(?:\\.0+)?|(?:(?:1[0-7]\\d)|(?:[1-9]?\\d))(?:\\.\\d+)?))\\s*$
REGEX_SQL;
        } else {
            $regexSql = <<<'REGEX_SQL'
^\\s*(?<latitude>[+-]?(?:[1-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?))[^\\d.+-]+(?<longitude>[+-]?(?:180(?:\\.0+)?|(?:(?:1[0-7]\\d)|(?:[1-9]?\\d))(?:\\.\\d+)?))\\s*$
REGEX_SQL;
        }

        if ($isStrictLiteral) {
            // Process is quicker than regex here.
            $first = 'TRIM(SUBSTRING_INDEX(TRIM(`value`.`value`), ",", 1))';
            $second = 'TRIM(SUBSTRING_INDEX(TRIM(`value`.`value`), ",", -1))';
        } else {
            // The whole value is already checked, so a basic pattern is enough.
            // "[0-9]" instead of "\d" avoids a quadruple backslashes variable.
            $first = "REGEXP_SUBSTR(`value`.`value`, '[0-9.+-]+')";
            $second = "REGEXP_SUBSTR(REGEXP_SUBSTR(`value`.`value`, '[^0-9.+-]+[0-9.+-]+'), '[0-9.+-]+')";
        }

        if ($isLongLat) {
            $x = $first;
            $y = $second;
        } else {
            $x = $second;
            $y = $first;
        }

        $selectSql = <<<SQL
CONCAT("POINT(", $x, " ", $y, ")")
SQL;

        // Process only literal strings to avoid to reprocess geometric data.
        $whereSql = <<<SQL
WHERE
    `value`.`resource_id` IN (:resource_ids)
    AND `value`.`type` = "literal"
    AND `value`.`value` REGEXP '$regexSql'
SQL;
        $whereSql .= "\n    " . $sqlWhere;

        // The update of the table `data_type_geometry` should be done first in
        // order to keep same results from the database.
        $sql = <<<SQL
INSERT INTO `data_type_geography`
    (`resource_id`, `property_id`, `value`)
SELECT DISTINCT
    `value`.`resource_id`,
    `value`.`property_id`,
    ST_GeomFromText($selectSql, $srid)
FROM `value`
$whereSql
ON DUPLICATE KEY UPDATE
    `data_type_geography`.`id` = `data_type_geography`.`id`
;
SQL;
        $connection->executeStatement($sql, $bind, $types);

        // Normalize existing values when needed.
        $sql = <<<SQL
UPDATE `value`
SET
    `value`.`type` = "geography:coordinates",
    `value`.`value` = CONCAT($y, ",", $x)
$whereSql
SQL;
        $connection->executeStatement($sql, $bind, $types);
    }

    protected function copyCoordinatesToFeatures(array $ids, array $data): void
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
INSERT INTO `mapping_feature`
    (`item_id`, `media_id`, `label`, `geography`)
SELECT DISTINCT
    `value`.`resource_id`,
    NULL,
    NULL,
    ST_GeomFromText(CONCAT(
        "POINT(",
        SUBSTRING_INDEX(`value`.`value`, ",", -1),
        " ",
        SUBSTRING_INDEX(`value`.`value`, ",", 1),
        ")"
    ))
FROM `value`
LEFT JOIN `mapping_feature`
    ON `mapping_feature`.`item_id` = `value`.`resource_id`
        AND ST_AsText(`mapping_feature`.`geography`) = CONCAT(
            "POINT(",
            SUBSTRING_INDEX(`value`.`value`, ",", -1),
            " ",
            SUBSTRING_INDEX(`value`.`value`, ",", 1),
            ")"
        )
WHERE
    `value`.`resource_id` IN (:resource_ids)
    AND `value`.`type` = "geography:coordinates"
    AND `mapping_feature`.`id` IS NULL
    $sqlWhere
;
SQL;
        $connection->executeStatement($sql, $bind, $types);
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
    AND `value`.`type` = "geography:coordinates"
    AND `mapping_marker`.`id` IS NULL
    $sqlWhere
;
SQL;
        $connection->executeStatement($sql, $bind, $types);
    }

    protected function copyFeaturesToCoordinates(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // The srid is no more needed because mapping features are geography.
        // Nevertheless, multiple srid cannot be managed.
        // $srid = $data['geometry']['srid'] ?? Geography::DEFAULT_SRID;

        $bind = [
            'resource_ids' => $ids,
            'property_id' => (int) $data['geometry']['to_property_id'],
        ];
        $types = ['resource_ids' => $connection::PARAM_INT_ARRAY];

        $fromSql = <<<SQL
FROM `mapping_feature`
LEFT JOIN `value`
    ON `value`.`resource_id` = `mapping_feature`.`item_id`
        AND `value`.`type` = "geography:coordinates"
        AND ST_AsText(`mapping_feature`.`geography`) = CONCAT(
            "POINT(",
            SUBSTRING_INDEX(`value`.`value`, ",", -1),
            " ",
            SUBSTRING_INDEX(`value`.`value`, ",", 1),
            ")"
        )
WHERE
    `mapping_feature`.`item_id` IN (:resource_ids)
    AND `value`.`id` IS NULL
;
SQL;

        // The update of the table `data_type_geometry` should be done first in
        // order to keep same results from the database.
        $sql = <<<SQL
INSERT INTO `data_type_geography`
    (`resource_id`, `property_id`, `value`)
SELECT DISTINCT
    `mapping_feature`.`item_id`,
    :property_id,
    `mapping_feature`.`geography`
$fromSql
SQL;
        $connection->executeStatement($sql, $bind, $types);

$sql = <<<SQL
INSERT INTO `value`
    (`resource_id`, `property_id`, `value_resource_id`, `type`, `lang`, `value`, `uri`, `is_public`)
SELECT DISTINCT
    `mapping_feature`.`item_id`,
    :property_id,
    NULL,
    'geography:coordinates',
    NULL,
    CONCAT(ST_Y(`mapping_feature`.`geography`), ",", ST_X(`mapping_feature`.`geography`)),
    NULL,
    1
$fromSql
SQL;
        $connection->executeStatement($sql, $bind, $types);
    }

    protected function copyMarkersToCoordinates(array $ids, array $data): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $srid = $data['geometry']['srid'] ?? Geography::DEFAULT_SRID;

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
        AND `value`.`type` = "geography:coordinates"
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
        $connection->executeStatement($sql, $bind, $types);

        $sql = <<<SQL
INSERT INTO `value`
    (`resource_id`, `property_id`, `value_resource_id`, `type`, `lang`, `value`, `uri`, `is_public`)
SELECT DISTINCT
    `mapping_marker`.`item_id`,
    :property_id,
    NULL,
    "geography:coordinates",
    NULL,
    CONCAT(`mapping_marker`.`lat`, ",", `mapping_marker`.`lng`),
    NULL,
    1
$fromSql
SQL;
        $connection->executeStatement($sql, $bind, $types);
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
     * Unlike NumericDataTypes, the same table is used for multiple types.
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

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $srid = $services->get('Omeka\Settings')
            ->get('datatypegeometry_locate_srid', Geography::DEFAULT_SRID);

        // All rows in data_type_geometry and data_type_geography are removed,
        // so get all ids one time.
        $entityValues = $entity->getValues();

        $entityDataTypeValues = [
            'geography' => [],
            'geometry' => [],
        ];
        if ($entity->getId()) {
            foreach (array_keys($entityDataTypeValues) as $mainGeo) {
                $mainGeoUpper = ucfirst($mainGeo);
                $dql = "SELECT dtv FROM \DataTypeGeometry\Entity\DataType$mainGeoUpper dtv WHERE dtv.resource = :resource";
                $query = $entityManager->createQuery($dql);
                $query->setParameter('resource', $entity);
                $entityDataTypeValues[$mainGeo] = $query->getResult();
            }
        }

        /** @var \DataTypeGeometry\DataType\AbstractDataType $dataType */
        foreach ($this->getGeometryDataTypes() as $dataTypeName => $dataType) {
            // TODO Improve criteria when the types are mixed in a property.
            $criteria = Criteria::create()->where(Criteria::expr()->eq('type', $dataTypeName));
            $matchingValues = $entityValues->matching($criteria);
            // This resource has no data values of this type.
            if (!count($matchingValues)) {
                continue;
            }

            // TODO Remove this persist, that is used only when a geometry is updated on the map.
            // Persist is required for annotation, since there is no cascade
            // persist between annotation and values.
            if ($entity instanceof \Annotate\Entity\Annotation) {
                $entityManager->persist($entity);
            }

            $mainGeo = strtok($dataTypeName, ':');
            $currentSrid = $mainGeo === 'geography' ? $srid : 0;

            $dataTypeClass = $dataType->getEntityClass();

            /** @var \DataTypeGeometry\Entity\DataTypeGeometry[] $existingDataValues */
            $existingDataValues = &$entityDataTypeValues[$mainGeo];

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

                // Set the default srid for geographies in all cases.
                /** @var \LongitudeOne\Spatial\PHP\Types\Geography\GeographyInterface|\LongitudeOne\Spatial\PHP\Types\Geometry\GeometryInterface $geometry */
                $geometry = $dataType->getGeometryFromValue($value->getValue());
                if ($geometry instanceof GeographyInterface) {
                    $geometry->setSrid($currentSrid);
                }

                $dataValue->setResource($entity);
                $dataValue->setProperty($value->getProperty());
                $dataValue->setValue($geometry);
            }

            unset($existingDataValues);
        }

        // Remove any data values that weren't reused.
        foreach ($entityDataTypeValues as &$existingDataValues) {
            foreach ($existingDataValues as $existingDataValue) {
                if ($existingDataValue !== null) {
                    $entityManager->remove($existingDataValue);
                }
            }
        }
        unset($existingDataValues);
    }

    /**
     * @todo Remove the fix of srid in geographic table after save (check dependency?).
     */
    public function fixSridInDatabase(Event $event): void
    {
        static $supportGeography = null;

        if ($supportGeography === false) {
            return;
        }

        $services = $this->getServiceLocator();
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $srid = (int) $services->get('Omeka\Settings')->get('datatypegeometry_locate_srid', Geography::DEFAULT_SRID);
        $sql = <<<SQL
UPDATE `data_type_geography`
SET `value` = ST_SRID(`value`, $srid)
WHERE ST_SRID(`value`) != $srid;
SQL;
        try {
            $connection->executeStatement($sql);
            $supportGeography = true;
        } catch (\Exception $e) {
            $supportGeography = false;
        }
    }

    /**
     * Get all data types added by this module.
     *
     * @return \Omeka\DataType\AbstractDataType[]
     */
    protected function getGeometryDataTypes()
    {
        $dataTypes = $this->getServiceLocator()->get('Omeka\DataTypeManager');
        return [
            'geography' => $dataTypes->get('geography'),
            'geography:coordinates' => $dataTypes->get('geography:coordinates'),
            'geometry' => $dataTypes->get('geometry'),
            'geometry:coordinates' => $dataTypes->get('geometry:coordinates'),
            'geometry:position' => $dataTypes->get('geometry:position'),
        ];
    }
}
