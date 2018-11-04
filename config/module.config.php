<?php
namespace Cartography;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'cartography' => View\Helper\Cartography::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\CartographyController::class => Controller\Admin\CartographyController::class,
            Controller\Site\CartographyController::class => Controller\Site\CartographyController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'cartography' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cartography',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cartography\Controller\Site',
                                'controller' => Controller\Site\CartographyController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'cartography' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cartography',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cartography\Controller\Admin',
                                'controller' => Controller\Admin\CartographyController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        '[Untitled]', // @translate
        'Annotation #', // @translate
        'Cancel', // @translate
        'Cancel Styling', // @translate
        'Choose another element you want to style', // @translate
        'Click on the element you want to style', // @translate
        'Finish', // @translate
        'Image #', // @translate
        'Layer', // @translate
        'Log in to delete the geometry.', // @translate
        'Log in to edit the geometry.', // @translate
        'Log in to save the geometry.', // @translate
        'No overlay', // @translate
        'Related item', // @translate
        'Related items', // @translate
        'Related items:', // @translate
        'Remove value', // @translate
        'Save', // @translate
        'Save Styling', // @translate
        'The resource is already linked to the current annotation.', // @translate
        'There is no image attached to this resource.', // @translate
        'Unable to delete the geometry.', // @translate
        'Unable to delete the geometry: no identifier.', // @translate
        'Unable to fetch the geometries.', // @translate
        'Unable to find the geometry.', // @translate
        'Unable to save the geometry.', // @translate
        'Unable to save the edited geometry: no identifier.', // @translate
        'Unable to update the geometry.', // @translate
        'Uncertainty:', // @translate
    ],
    'cartography' => [
        'config' => [
            'cartography_user_guide' => 'Feel free to use <strong>Cartography</strong>!', // @translate
            'cartography_display_tab' => [
                'describe',
                'locate',
            ],
            'cartography_js_describe' => '',
            'cartography_js_locate' => '',
        ],
        'site_settings' => [
            'cartography_append_public' => [
                'describe_item_sets_show',
                'describe_items_show',
                'describe_media_show',
                'locate_item_sets_show',
                'locate_items_show',
                'locate_media_show',
            ],
            'cartography_annotate' => false,
        ],
        'dependencies' => [
            'Annotate',
        ],
    ],
];
