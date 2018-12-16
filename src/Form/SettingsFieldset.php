<?php
namespace DataTypeGeometry\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Data Type Geometry'; // @translate

    public function init()
    {
        $this->add([
            'name' => 'datatypegeometry_buttons',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Buttons for resource forms', // @translate
                'value_options' => [
                    '' => 'None', // @translate
                    'geometry:geometry' => 'Geometry', // @translate
                    'geometry:geography' => 'Geography', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'datatypegeometry_buttons',
            ],
        ]);
    }
}