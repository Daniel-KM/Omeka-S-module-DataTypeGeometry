<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class BatchEditFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->setName('geometry')
            ->setOptions([
                    'label' => 'Geometry and geography', // @translate
                ])
            ->setAttributes([
                'id' => 'geometry',
                'class' => 'field-container',
                // This attribute is required to make "batch edit all" working.
                'data-collection-action' => 'replace',
            ]);
        $this
            ->add([
                'name' => 'manage_coordinates_markers',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Manage geographic coordinates', // @translate
                    'value_options' => [
                        // 'sync' => 'Synchronize coordinates and mapping markers', // @translate
                        'coordinates_to_markers' => 'Copy coordinates to mapping markers', // @translate
                        // 'markers_to_coordinates' => 'Copy mapping markers to coordinates', // @translate
                    ],
                    'empty_option' => '[No change]' // @translate
                ],
                'attributes' => [
                    'id' => 'manage_coordinates_markers',
                    'class' => 'chosen-select',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ]);
    }
}
