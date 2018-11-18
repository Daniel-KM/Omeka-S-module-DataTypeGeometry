<?php
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Cartography\Form\Element;

use Omeka\Form\Element\AbstractGroupByOwnerSelect;

class ResourceTemplateSelect extends AbstractGroupByOwnerSelect
{
    public function getResourceName()
    {
        return 'resource_templates';
    }

    public function getValueLabel($resource)
    {
        return $resource->label();
    }
}
