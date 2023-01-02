<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use DataTypeGeometry\DataType\Geography;
use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'datatypegeometry_locate_srid',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Spatial reference id (Srid) for Locate', // @translate
                'info' => 'The Srid allows to take the curvature of the Earth into account for map.
Recommended: 0 or 4326.
It is displayed in the json-ld output.', // @translate
            ],
            'attributes' => [
                'id' => 'datatypegeometry_locate_srid',
                'min' => 0,
                'max' => 99999,
                'step' => 1,
                'placeholder' => Geography::DEFAULT_SRID,
            ],
        ]);

        $this->add([
            'name' => 'process_mode',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Process job in background', // @translate
                'value_options' => [
                    'common' => 'Common (update values, reindex resources and annotations as geometries, and targets according to data)',
                    'resources reindex' => 'Reindex resources without update of values', // @translate
                    'resources geometry' => 'Set resources as geometry', // @translate
                    'resources geography' => 'Set resources as geography', // @translate
                    'annotations reindex' => 'Reindex annotations without update of values', // @translate
                    'annotations geometry' => 'Set annotations as geometry', // @translate
                    'annotations geography' => 'Set annotations as geography', // @translate
                    'cartography' => 'Annotation targets (geometry if image, geography if map)', // @translate
                    'check' => 'Basic check any geo value well-formedness', // @translate
                    'check geometry' => 'Basic check geometries well-formedness', // @translate
                    'check geography' => 'Basic check geographies well-formedness', // @translate
                    'fix linestring' => 'Replace bad linestrings by points', // @translate
                    'truncate' => 'Remove all indexes', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'process_mode',
            ],
        ]);

        // Fix the formatting issue of the label in Omeka.
        $this->get('process_mode')->setLabelAttributes(['style' => 'display: inline-block']);

        $this->add([
            'name' => 'process',
            'type' => Element\Submit::class,
            'options' => [
                'label' => 'Run in background', // @translate
            ],
            'attributes' => [
                'id' => 'process',
                'value' => 'Process', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'datatypegeometry_locate_srid',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'process_mode',
            'required' => false,
        ]);
    }
}
