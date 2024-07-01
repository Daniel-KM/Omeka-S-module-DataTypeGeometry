<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class BatchEditFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->setName('geometry')
            ->setOptions([
                'element_group' => 'geometry',
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
                    'element_group' => 'geometry',
                    'label' => 'Convert literal values to coordinates', // @translate
                ],
                'attributes' => [
                    'id' => 'geometry_convert_literal_to_coordinates',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'convert_literal_order',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'geometry',
                    'label' => 'Order of literal value', // @translate
                    'value_options' => [
                        'latitude_longitude' => 'Latitude then longitude (most frequent)', // @translate
                        'longitude_latitude' => 'Longitude then latitude', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'geometry_convert_literal_order',
                    'value' => 'latitude_longitude',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'convert_literal_strict',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'geometry',
                    'label' => 'Check format strictly ("," as separator)', // @translate
                ],
                'attributes' => [
                    'id' => 'geometry_convert_literal_strict',
                    'value' => 'latitude_longitude',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'manage_coordinates_features',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'geometry',
                    'label' => 'Manage geographic coordinates for module Mapping', // @translate
                    'value_options' => [
                        'sync' => 'Synchronize coordinates and mapping markers', // @translate
                        'coordinates_to_features' => 'Copy coordinates to mapping markers', // @translate
                        'features_to_coordinates' => 'Copy mapping markers to coordinates', // @translate
                    ],
                    'empty_option' => '[No change]', // @translate
                ],
                'attributes' => [
                    'id' => 'geometry_manage_coordinates_features',
                    'class' => 'chosen-select',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'from_properties',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'geometry',
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
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'geometry',
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
