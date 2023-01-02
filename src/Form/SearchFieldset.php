<?php declare(strict_types=1);

namespace DataTypeGeometry\Form;

use DataTypeGeometry\View\Helper\DatabaseVersion;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SearchFieldset extends Fieldset
{
    /**
     * @var \DataTypeGeometry\View\Helper\DatabaseVersion
     */
    protected $databaseVersion;

    /**
     * The distinction between geometry/geogarphy is done with js (labels and units).
     *
     * Warning: MariaDB does not support geographic search.
     * @see https://mariadb.com/kb/en/st_srid
     *
     * @see \DataTypeGeometry\View\Helper\NormalizeGeometryQuery
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element::init()
     */
    public function init(): void
    {
        $supportGeographicSearch = $this->databaseVersion->supportGeographicSearch()
            && $this->databaseVersion->isDatabaseRecent();

        $this->setName('geo');
        if ($supportGeographicSearch) {
            $this->setLabel('Geometry / Geography');
            $this
                ->add([
                    'name' => 'mode',
                    'type' => Element\Radio::class,
                    'options' => [
                        'value_options' => [
                            'geometry' => 'Geometry', // @translate
                            'geography' => 'Geography', // @translate
                        ],
                    ],
                    'attributes' => [
                        'id' => 'geo-mode',
                        'value' => 'geometry',
                    ],
                ]);
        } else {
            $this->setLabel('Geometry');
            $this
                ->add([
                    'name' => 'mode',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => 'geo-mode',
                        'value' => 'geometry',
                    ],
                ]);
        }

        $this->geography($supportGeographicSearch);
    }

    protected function geography(bool $supportGeographicSearch): self
    {
        $this
            ->add([
                'type' => Fieldset::class,
                'name' => 'around',
                'options' => [
                    'label' => 'Around a point', // @translate
                ],
            ]);
        $around = $this->get('around');
        $around
            ->add([
                'name' => 'x',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'x',
                ],
                'attributes' => [
                    'id' => 'geo-around-x',
                    'class' => 'query-geo-around-x',
                    'placeholder' => 'x', // @translate
                    'data-geo-mode' => 'geometry',
                ],
            ])
            ->add([
                'name' => 'y',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'y', // @translate
                ],
                'attributes' => [
                    'id' => 'geo-around-y',
                    'class' => 'query-geo-around-y',
                    'placeholder' => 'y', // @translate
                    'data-geo-mode' => 'geometry',
                ],
            ])
        ;
        if ($supportGeographicSearch) {
            $around
                ->add([
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
                        'step' => '0.000001',
                        'placeholder' => 'Latitude', // @translate
                        'data-geo-mode' => 'geography',
                    ],
                ])
                ->add([
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
                        'step' => '0.000001',
                        'placeholder' => 'Longitude', // @translate
                        'data-geo-mode' => 'geography',
                    ],
                ]);
        }
        $around
            ->add([
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
                    'step' => '0.001',
                    'placeholder' => 'Radius', // @translate
                    'aria-label' => 'Radius', // @translate
                ],
            ]);
        if ($supportGeographicSearch) {
            $around
                ->add([
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
                        'data-geo-mode' => 'geography',
                    ],
                ]);
        }

        $this
            ->add([
                'name' => 'box',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Inside a box', // @translate
                ],
                'attributes' => [
                    'id' => 'geo-mapbox',
                    'class' => 'query-geo-box',
                    'placeholder' => 'Top left x y Bottom right x y', // @translate
                    'aria-label' => 'Rectangle box with two opposite coordinates', // @translate
                    'data-geo-mode' => 'geometry',
                ],
            ])
            ->add([
                'name' => 'zone',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Within a zone', // @translate
                ],
                'attributes' => [
                    'id' => 'geo-area',
                    'class' => 'query-geo-zone',
                    'placeholder' => 'POLYGON ((51.34 2.42, 48.51 -5.71, 43.04 -1.84, 42.15 3.66, 43.54 7.71, 48.95 8.28, 51.34 2.42))',
                    'aria-label' => 'WKT (well-known text that represents a geometry)', // @translate
                    'data-geo-mode' => 'geometry',
                ],
            ])
        ;

        if ($supportGeographicSearch) {
            $this
                ->add([
                    'name' => 'mapbox',
                    'type' => Element\Text::class,
                    'options' => [
                        'label' => 'Inside a box', // @translate
                    ],
                    'attributes' => [
                        'id' => 'geo-mapbox',
                        'class' => 'query-geo-mapbox',
                        'placeholder' => 'Top left latitude longitude Bottom right latitude longitude', // @translate
                        'data-geometry-placeholder' => 'Top left x y Bottom right x y', // @translate
                        'data-geography-placeholder' => 'Top left latitude longitude Bottom right latitude longitude', // @translate
                        'aria-label' => 'Rectangle box with two opposite geo-coordinates', // @translate
                        'data-geo-mode' => 'geography',
                    ],
                ])
                ->add([
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
                        'data-geo-mode' => 'geography',
                    ],
                ]);
        }

        return $this;
    }

    public function setDatabaseVersion(DatabaseVersion $databaseVersion): self
    {
        $this->databaseVersion = $databaseVersion;
        return $this;
    }
}
