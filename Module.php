<?php
namespace DataTypeGeometry;

// TODO Remove this requirement.
require_once __DIR__ . '/src/Module/AbstractGenericModule.php';

use DataTypeGeometry\Module\AbstractGenericModule;
use Doctrine\Common\Collections\Criteria;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Data type Geometry
 *
 * Adds a data type Geometry to properties of resources and allows to manage
 * values in Omeka or an an external database.
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractGenericModule
{
    public function onBootstrap(MvcEvent $event)
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

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        // In case of upgrade of a recent version of Cartography, the database
        // may exist.
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = 'SHOW tables LIKE "data_type_geometry";';
        $stmt = $connection->query($sql);
        $table = $stmt->fetchColumn();
        if ($table) {
            return;
        }

        $this->setServiceLocator($serviceLocator);

        if (!$this->supportSpatialSearch()) {
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
            $messenger->addWarning(sprintf('Your database does not support advanced spatial search. See the minimum requirements in readme.')); // @translate
        }

        $useMyIsam = $this->requireMyIsamToSupportGeometry();
        if ($useMyIsam) {
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
            $messenger->addWarning(sprintf('Your database does not support modern spatial indexing. See the minimum requirements in readme.')); // @translate
        }

        $filepath = $useMyIsam
            ? $this->modulePath() . '/data/install/schema-myisam.sql'
            :  $this->modulePath() . '/data/install/schema.sql';
        $this->execSqlFromFile($filepath);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            \Annotate\Controller\Admin\AnnotationController::class,
        ];
        foreach ($controllers as $controller) {
            // Manage the geometry data type.
            $sharedEventManager->attach(
                $controller,
                'view.add.after',
                [$this, 'prepareResourceForm']
            );
            $sharedEventManager->attach(
                $controller,
                'view.edit.after',
                [$this, 'prepareResourceForm']
            );
        }

        $controllers[] = 'Omeka\Controller\Site\Item';
        $controllers[] = 'Omeka\Controller\Site\ItemSet';
        $controllers[] = 'Omeka\Controller\Site\Media';
        $controllers[] = \Annotate\Controller\Site\AnnotationController::class;
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
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function searchQuery(Event $event)
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
    public function displayAdvancedSearch(Event $event)
    {
        $this->prepareResourceForm($event);
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
    public function filterSearchFilters(Event $event)
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
     * Prepare resource forms for geometry data type.
     *
     * @param Event $event
     */
    public function prepareResourceForm(Event $event)
    {
        $view = $event->getTarget();
        $headScript = $view->headScript();
        // $settings = $this->getServiceLocator()->get('Omeka\Settings');
        // $datatypes = $settings->get('cartography_datatypes', []);
        $datatypes = ['geometry'];
        $headScript->appendScript('var geometryDatatypes = ' . json_encode($datatypes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';');
        $headScript->appendFile($view->assetUrl('vendor/terraformer/terraformer.min.js', __NAMESPACE__));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser.min.js', __NAMESPACE__));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser.min.js', __NAMESPACE__));
        $headScript->appendFile($view->assetUrl('js/data-type-geometry.js', __NAMESPACE__));
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
    public function saveGeometryData(Event $event)
    {
        $entity = $event->getParam('entity');
        if (!$entity instanceof \Omeka\Entity\Resource) {
            // This is not a resource.
            return;
        }

        $entityValues = $entity->getValues();

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        foreach ($this->getGeometryDataTypes() as $dataTypeName => $dataType) {
            $criteria = Criteria::create()->where(Criteria::expr()->eq('type', $dataTypeName));
            $matchingValues = $entityValues->matching($criteria);
            // This resource has no data values of this type.
            if (!count($matchingValues)) {
                continue;
            }

            $dataTypeClass = $dataType->getEntityClass();

            // TODO Remove this persist, that is used only when a geometry is updated on the map.
            // Persist is required for annotation, since there is no cascade persist
            // between annotation and values.
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
                    // data values are already managed and will update during flush.
                    $existingDataValues[key($existingDataValues)] = null;
                    next($existingDataValues);
                }

                $geometry = $dataType->getGeometryFromValue($value->getValue());
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
        if (empty($db)) {
            return true;
        }
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
        if (empty($db)) {
            return false;
        }
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
     * @return array
     */
    protected function databaseVersion()
    {
        $result = [];

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        $sql = 'SHOW VARIABLES LIKE "version";';
        $stmt = $connection->query($sql);
        $version= $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);

        $isMySql = stripos($version, 'mysql') !== false;
        $result['version'] = $version;
        if ($isMySql) {
            $result['db'] = 'mysql';
            return $result;
        }

        $isMariaDb = stripos($version, 'mariadb') !== false;
        if ($isMariaDb) {
            $result['db'] = 'mariadb';
            return $result;
        }

        $sql = 'SHOW VARIABLES LIKE "innodb_version";';
        $stmt = $connection->query($sql);
        $version= $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);
        $isInnoDb = !empty($version);
        if ($isInnoDb) {
            $result['db'] = 'innodb';
            $result['version'] = $version;
            return $result;
        }
    }
}
