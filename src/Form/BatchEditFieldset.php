<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\PropertySelect;

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
            ])

            ->add([
                'name' => 'convert_literal_to_coordinates',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Convert literal values to coordinates', // @translate
                ],
                'attributes' => [
                    'id' => 'geometry_convert_literal_to_coordinates',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'manage_coordinates_markers',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Manage geographic coordinates', // @translate
                    'value_options' => [
                        'sync' => 'Synchronize coordinates and mapping markers', // @translate
                        'coordinates_to_markers' => 'Copy coordinates to mapping markers', // @translate
                        'markers_to_coordinates' => 'Copy mapping markers to coordinates', // @translate
                    ],
                    'empty_option' => '[No change]', // @translate
                ],
                'attributes' => [
                    'id' => 'geometry_manage_coordinates_markers',
                    'class' => 'chosen-select',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'from_properties',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Source properties to create markers or to convert from literal', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'all' => '[All properties]', // @translate
                    ],
                    'empty_option' => '',
                    'used_terms' => true,
                ],
                'attributes' => [
                    'id' => 'geometry_from_property',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'required' => false,
                    'data-placeholder' => 'Select properties…', // @translate
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'to_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property where to copy markers', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                    'used_terms' => true,
                ],
                'attributes' => [
                    'id' => 'geometry_to_property',
                    'class' => 'chosen-select',
                    'multiple' => false,
                    'required' => false,
                    'data-placeholder' => 'Select property…', // @translate
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ]);
    }
}
