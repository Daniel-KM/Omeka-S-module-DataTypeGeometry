<?php declare(strict_types=1);

namespace DataTypeGeometry\View\Helper;

use DataTypeGeometry\Form\SearchFieldset;
use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;

class GeometryFieldset extends AbstractHelper
{
    /**
     * @var SearchFieldset
     */
    protected $searchFieldset;

    /**
     * @param SearchFieldset $searchFielset
     */
    public function __construct(SearchFieldset $searchFielset)
    {
        $this->searchFieldset = $searchFielset;
    }

    /**
     * Get the geometry search fieldset
     *
     * @return \DataTypeGeometry\Form\SearchFieldset
     */
    public function __invoke()
    {
        $fieldset = $this->searchFieldset;
        $fieldset->init();
        $fieldset->prepareElement(new Form);
        return $fieldset;
    }
}
