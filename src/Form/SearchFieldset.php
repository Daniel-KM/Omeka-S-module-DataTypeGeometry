<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SearchFieldset extends Fieldset
{
    public function init(): void
    {
        // Because the srid below is set to 4326 by default, this is a form for
        // a geographic query.

        $this->setName('geo');
        $this->setLabel('Geography');

        // Around a point.
        $this->add([
            'type' => Fieldset::class,
            'name' => 'around',
            'options' => [
                'label' => 'Around a point', // @translate
            ],
        ]);
        $around = $this->get('around');

        $around->add([
            'name' => 'latitude',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Latitude', // @translate
            ],
            'attributes' => [
                'id' => 'geo-around-latitude',
                'class' => 'query-geo-around-latitude',
                'min' => '-90',
                'max' => '90',
                'step' => '1',
                'placeholder' => 'Latitude', // @translate
                'aria-label' => 'Latitude',
            ],
        ]);

        $around->add([
            'name' => 'longitude',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Longitude', // @translate
            ],
            'attributes' => [
                'id' => 'geo-around-longitude',
                'class' => 'query-geo-around-longitude',
                'min' => '-180',
                'max' => '180',
                'step' => '1',
                'placeholder' => 'Longitude', // @translate
                'aria-label' => 'Longitude', // @translate
            ],
        ]);

        $around->add([
            'name' => 'radius',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Radius', // @translate
            ],
            'attributes' => [
                'id' => 'geo-around-radius',
                'class' => 'query-geo-around-radius',
                'min' => '1',
                'max' => '20038000',
                'step' => '1',
                'placeholder' => 'Radius', // @translate
                'aria-label' => 'Radius', // @translate
            ],
        ]);

        $around->add([
            'name' => 'unit',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Unit', // @translate
                'value_options' => [
                    'km' => 'kilometres', // @translate
                    'm' => 'metres', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'geo-around-unit',
                'class' => 'query-geo-around-unit',
                'placeholder' => 'Unit', // @translate
                'aria-label' => 'Unit of the radius for geographic point', // @translate
            ],
        ]);

        $this->add([
            'name' => 'mapbox',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Inside a box', // @translate
            ],
            'attributes' => [
                'id' => 'geo-mapbox',
                'class' => 'query-geo-mapbox',
                'placeholder' => 'Top left latitude longitude Bottom right latitude longitude', // @translate
                'aria-label' => 'Rectangle box with two opposite geo-coordinates', // @translate
            ],
        ]);

        $this->add([
            'name' => 'area',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Within an area', // @translate
            ],
            'attributes' => [
                'id' => 'geo-area',
                'class' => 'query-geo-area',
                'placeholder' => 'POLYGON ((2.42 51.34, -5.71 48.51, -1.84 43.04, 3.66 42.15, 7.71 43.54, 8.28 48.95, 2.42 51.34))',
                'aria-label' => 'WKT (well-known text that represents a geometry)', // @translate
            ],
        ]);

        $this->add([
            'name' => 'srid',
            'type' => Element\Hidden::class,
            'attributes' => [
                'id' => 'geo-srid',
                'value' => 4326,
            ],
        ]);
    }
}
