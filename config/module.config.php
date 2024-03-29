<?php declare(strict_types=1);

namespace DataTypeGeometry;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\DataTypeGeography::class => Entity\DataTypeGeography::class,
            Entity\DataTypeGeometry::class => Entity\DataTypeGeometry::class,
        ],
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        // TODO Many Doctrine spatial functions are not used. Keep them to allow any dql queries?
        // The custom types are not loaded by the EntityManagerFactory, so they
        // are set in the bootstrap of the module.
        'functions' => [
            // See https://github.com/creof/doctrine2-spatial/blob/master/INSTALL.md.
            // The commented keys can be used by another module: just enable them.
            /*
            'string' => [
                // For PostgreSql.
                'geometry' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\Geometry::class,
                'stbuffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STBuffer::class,
                'stcollect' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCollect::class,
                'stsnaptogrid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSnapToGrid::class,
                'stoverlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STOverlaps::class,
            ],
            */
            'numeric' => [
                /*
                // For PostgreSql.
                'starea' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STArea::class,
                'stasbinary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsBinary::class,
                'stasgeojson' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsGeoJson::class,
                'stastext' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsText::class,
                'stazimuth' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAzimuth::class,
                'stboundary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STBoundary::class,
                'stcentroid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCentroid::class,
                'stclosestpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STClosestPoint::class,
                'stcontains' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContains::class,
                'stcontainsproperly' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContainsProperly::class,
                'stcoveredby' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCoveredBy::class,
                'stcovers' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCovers::class,
                'stcrosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCrosses::class,
                'stdisjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDisjoint::class,
                'stdistance' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistance::class,
                'stdistancesphere' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistanceSphere::class,
                'stdwithin' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDWithin::class,
                'stenvelope' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STEnvelope::class,
                'stexpand' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STExpand::class,
                'stextent' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STExtent::class,
                'stgeomfromtext' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STGeomFromText::class,
                'stintersection' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STIntersection::class,
                'stintersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STIntersects::class,
                'stlength' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLength::class,
                'stlinecrossingdirection' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLineCrossingDirection::class,
                'stlineinterpolatepoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLineInterpolatePoint::class,
                'stmakebox2d' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakeBox2D::class,
                'stmakeline' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakeLine::class,
                'stmakepoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakePoint::class,
                'stperimeter' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STPerimeter::class,
                'stpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STPoint::class,
                'stscale' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STScale::class,
                'stsetsrid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSetSRID::class,
                'stsimplify' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSimplify::class,
                'ststartpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STStartPoint::class,
                'stsummary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSummary::class,
                'sttouches' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STTouches::class,
                'sttransform' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STTransform::class,
                // For mySql.
                'area' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Area::class,
                'asbinary' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsBinary::class,
                'astext' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsText::class,
                'buffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Buffer::class,
                'centroid' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Centroid::class,
                'contains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Contains::class,
                'crosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Crosses::class,
                'dimension' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Dimension::class,
                'distance' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Distance::class,
                'disjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Disjoint::class,
                'distancefrommultyLine' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\DistanceFromMultyLine::class,
                'endpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\EndPoint::class,
                'envelope' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Envelope::class,
                'equals' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Equals::class,
                'exteriorring' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\ExteriorRing::class,
                'geodistpt' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeodistPt::class,
                'geometrytype' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeometryType::class,
                'geomfromtext' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeomFromText::class,
                'glength' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GLength::class,
                'interiorringn' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\InteriorRingN::class,
                'intersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Intersects::class,
                'isclosed' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsClosed::class,
                'isempty' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsEmpty::class,
                'issimple' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsSimple::class,
                'linestringfromwkb' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\LineStringFromWKB::class,
                'linestring' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\LineString::class,
                'mbrcontains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRContains::class,
                'mbrdisjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRDisjoint::class,
                'mbrequal' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBREqual::class,
                'mbrintersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRIntersects::class,
                'mbroverlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBROverlaps::class,
                'mbrtouches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRTouches::class,
                'mbrwithin' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRWithin::class,
                'numinteriorrings' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\NumInteriorRings::class,
                'numpoints' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\NumPoints::class,
                'overlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Overlaps::class,
                'pointfromwkb' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\PointFromWKB::class,
                'pointn' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\PointN::class,
                'point' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Point::class,
                'srid' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\SRID::class,
                'startpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\StartPoint::class,
                // 'st_buffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STBuffer::class,
                // 'st_contains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STContains::class,
                'st_crosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STCrosses::class,
                'st_disjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STDisjoint::class,
                // 'st_distance' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STDistance::class,
                'st_equals' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STEquals::class,
                'st_intersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STIntersects::class,
                'st_overlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STOverlaps::class,
                'st_touches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STTouches::class,
                'st_within' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STWithin::class,
                'touches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Touches::class,
                'within' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Within::class,
                'x' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\X::class,
                'y' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Y::class,
                */

                // Functions used by this module.

                'stdistancesphere' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistanceSphere::class,
                'mbrcontains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRContains::class,
                // Point in mySql, ST_Point in Postgre, so use only st_geomfromtext.
                'point' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Point::class,

                // Custom for this module (not yet available in Doctrine Spatial).
                // Note: this method cannot be a common one, since Postgre uses "ST_DistanceSphere".
                'st_distance_sphere' => Doctrine\ORM\Query\AST\Functions\MySql\STDistanceSphere::class,

                // Create alias to manage mySql and PostgreSql with one code.
                // Some extra postgre params may not be used.
                'st_buffer' => Doctrine\ORM\Query\AST\Functions\STBuffer::class,
                'st_contains' => Doctrine\ORM\Query\AST\Functions\STContains::class,
                'st_distance' => Doctrine\ORM\Query\AST\Functions\STDistance::class,
                'st_geomfromtext' => Doctrine\ORM\Query\AST\Functions\STGeomFromText::class,
                'st_x' => Doctrine\ORM\Query\AST\Functions\STX::class,
                'st_y' => Doctrine\ORM\Query\AST\Functions\STY::class,
            ],
        ],
    ],
    'data_types' => [
        'invokables' => [
            'geography' => DataType\Geography::class,
            'geography:coordinates' => DataType\GeographyCoordinates::class,
            'geometry' => DataType\Geometry::class,
            'geometry:coordinates' => DataType\GeometryCoordinates::class,
            // This is a position, not coordinates: the 0 is the top left corner
            // as used in image editor, iiif, alto, etc.
            'geometry:position' => DataType\GeometryPosition::class,
        ],
        'value_annotating' => [
            'geography',
            'geography:coordinates',
            'geometry',
            'geometry:coordinates',
            'geometry:position',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'databaseVersion' => Service\ViewHelper\DatabaseVersionFactory::class,
            'geometryFieldset' => Service\ViewHelper\GeometryFieldsetFactory::class,
            'normalizeGeometryQuery' => Service\ViewHelper\NormalizeGeometryQueryFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\BatchEditFieldset::class => Form\BatchEditFieldset::class,
        ],
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
            Form\SearchFieldset::class => Service\Form\SearchFieldsetFactory::class,
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
        'Geographic coordinates', // @translate
        'Geography', // @translate
        'Geometric coordinates', // @translate
        'Geometric position', // @translate
        'Geometry', // @translate
        'Please enter a latitude.', // @translate
        'Please enter a longitude.', // @translate
        'Please enter a radius.', // @translate
        'Please enter a valid latitude.', // @translate
        'Please enter a valid longitude.', // @translate
        'Please enter a valid radius.', // @translate
        'Please enter a valid radius in m.', // @translate
        'Please enter a valid radius in km.', // @translate
        'Please enter a valid wkt for the geometry.', // @translate
        '"multipoint", "multiline" and "multipolygon" are not supported for now. Use collection instead.', // @translate
        'Error in input.', // @translate
    ],
    'csv_import' => [
        'data_types' => [
            'geography' => [
                'label' => 'Geography', // @translate
                'adapter' => 'literal',
            ],
            'geography:coordinates' => [
                'label' => 'Geographic coordinates', // @translate
                'adapter' => 'literal',
            ],
            'geometry' => [
                'label' => 'Geometry', // @translate
                'adapter' => 'literal',
            ],
            'geometry:coordinates' => [
                'label' => 'Geometric coordinates', // @translate
                'adapter' => 'literal',
            ],
            'geometry:position' => [
                'label' => 'Geometric position', // @translate
                'adapter' => 'literal',
            ],
        ],
    ],
    'datatypegeometry' => [
        'config' => [
            'datatypegeometry_locate_srid' => 4326,
            'datatypegeometry_support_geographic_search' => false,
        ],
    ],
];
