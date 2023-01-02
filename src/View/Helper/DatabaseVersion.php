<?php declare(strict_types=1);

namespace DataTypeGeometry\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class DatabaseVersion extends AbstractHelper
{
    /**
     * @var array
     */
    protected $db;

    public function __construct(array $db)
    {
        $this->db = $db;
    }

    /**
     * Get some database data about version.
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get the database data.
     */
    public function data(): array
    {
        return $this->db;
    }

    /**
     * Check if Omeka database has minimum requirements to search geometries.
     *
     * @see readme.md.
     *
     * This minimum versions are required by Omeka anyway (mysql 5.6.4 and mariadb 10.0.5).
     */
    public function supportSpatialSearch(): bool
    {
        switch ($this->db['db']) {
            case 'mysql':
                return version_compare($this->db['version'], '5.6.1', '>=');
            case 'mariadb':
                return version_compare($this->db['version'], '5.3.3', '>=');
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
    public function requireMyIsamToSupportGeometry(): bool
    {
        switch ($this->db['db']) {
            case 'mysql':
                return version_compare($this->db['version'], '5.7.14', '<');
            case 'mariadb':
                return version_compare($this->db['version'], '10.2.2', '<');
            default:
                return false;
        }
    }

    /**
     * Check if the Omeka database is recent enough for good geometric search.
     */
    public function isDatabaseRecent(): bool
    {
        switch ($this->db['db']) {
            case 'mysql':
                return version_compare($this->db['version'], '5.7.6', '>=');
            case 'mariadb':
                return version_compare($this->db['version'], '10.2.2', '>=');
            default:
                return false;
        }
    }
}
