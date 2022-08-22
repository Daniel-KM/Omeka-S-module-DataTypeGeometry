<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Data Type Geometry'; // @translate

    public function init(): void
    {
        $this
            ->setAttribute('id', 'data-type-geometry')
            ->add([
                'name' => 'datatypegeometry_buttons',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Buttons for resource forms', // @translate
                    'value_options' => [
                        '' => 'None', // @translate
                        'geometry:geography:coordinates' => 'Geographic coordinates', // @translate
                        'geometry:geography' => 'Geography', // @translate
                        'geometry:geometry' => 'Geometry', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'datatypegeometry_buttons',
                ],
            ]);
    }
}
