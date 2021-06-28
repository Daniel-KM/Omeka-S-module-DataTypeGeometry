<?php declare(strict_types=1);

namespace DataTypeGeometry;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

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
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $html = parent::getConfigForm($renderer);
        $html = '<p>'
            . $renderer->translate('Reindex geometries or resources and annotations as geometry or geography.') // @translate
            . '</p>'
            . $html;
        return $html;
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
                    && $dataTypeName === 'geometry:geography'
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
}
