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
        'data_types' => [
            'geography' => \LongitudeOne\Spatial\DBAL\Types\GeographyType::class,
            'geometry' => \LongitudeOne\Spatial\DBAL\Types\GeometryType::class,
        ],
        // TODO Many Doctrine spatial functions are not used. Keep them to allow any dql queries?
        'functions' => [
            // The commented keys can be used by another module: just enable them.
            // The function may be "string", "numeric" or other. Check it first when needed.
            'numeric' => [
                // Standard.
                /*
                'ST_Area' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StArea::class,
                'ST_AsBinary' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StAsBinary::class,
                'ST_AsText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StAsText::class,
                'ST_Boundary' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StBoundary::class,
                'ST_Buffer' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StBuffer::class,
                'ST_Centroid' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StCentroid::class,
                'ST_Contains' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StContains::class,
                'ST_ConvexHull' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StConvexHull::class,
                'ST_Crosses' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StCrosses::class,
                'ST_Difference' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDifference::class,
                'ST_Dimension' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDimension::class,
                'ST_Disjoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDisjoint::class,
                'ST_Distance' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDistance::class,
                'ST_EndPoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StEndPoint::class,
                'ST_Envelope' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StEnvelope::class,
                'ST_Equals' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StEquals::class,
                'ST_ExteriorRing' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StExteriorRing::class,
                'ST_GeometryN' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeometryN::class,
                'ST_GeometryType' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeometryType::class,
                'ST_GeomFromText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeomFromText::class,
                'ST_GeomFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeomFromWkb::class,
                'ST_InteriorRingN' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StInteriorRingN::class,
                'ST_Intersection' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIntersection::class,
                'ST_Intersects' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIntersects::class,
                'ST_IsClosed' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIsClosed::class,
                'ST_IsEmpty' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIsEmpty::class,
                'ST_IsRing' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIsRing::class,
                'ST_IsSimple' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIsSimple::class,
                'ST_Length' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StLength::class,
                'ST_LineStringFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StLineStringFromWkb::class,
                'ST_MLineFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StMLineFromWkb::class,
                'ST_MPointFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StMPointFromWkb::class,
                'ST_MPolyFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StMPolyFromWkb::class,
                'ST_NumGeometries' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StNumGeometries::class,
                'ST_NumInteriorRing' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StNumInteriorRing::class,
                'ST_NumPoints' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StNumPoints::class,
                'ST_Overlaps' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StOverlaps::class,
                'ST_Perimeter' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPerimeter::class,
                'ST_PointFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPointFromWkb::class,
                'ST_PointN' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPointN::class,
                'ST_PointOnSurface' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPointOnSurface::class,
                'ST_Point' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPoint::class,
                'ST_PolyFromWkb' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPolyFromWkb::class,
                'ST_Relate' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StRelate::class,
                'ST_SetSRID' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StSetSRID::class,
                'ST_Srid' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StSrid::class,
                'ST_StartPoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StStartPoint::class,
                'ST_SymDifference' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StSymDifference::class,
                'ST_Touches' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StTouches::class,
                'ST_Union' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StUnion::class,
                'ST_Within' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StWithin::class,
                'ST_X' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StX::class,
                'ST_Y' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StY::class,
                // Mysql.
                'SP_Buffer' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpBuffer::class,
                'SP_BufferStrategy' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpBufferStrategy::class,
                'SP_Distance' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpDistance::class,
                'SP_GeometryType' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpGeometryType::class,
                'SP_LineString' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpLineString::class,
                'SP_MbrContains' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrContains::class,
                'SP_MbrDisjoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrDisjoint::class,
                'SP_MbrEquals' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrEquals::class,
                'SP_MbrIntersects' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrIntersects::class,
                'SP_MbrOverlaps' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrOverlaps::class,
                'SP_MbrTouches' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrTouches::class,
                'SP_MbrWithin' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrWithin::class,
                'SP_Point' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpPoint::class,
                // PosgreSql.
                'SP_AsGeoJson' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpAsGeoJson::class,
                'SP_Azimuth' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpAzimuth::class,
                'SP_ClosestPoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpClosestPoint::class,
                'SP_Collect' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpCollect::class,
                'SP_ContainsProperly' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpContainsProperly::class,
                'SP_CoveredBy' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpCoveredBy::class,
                'SP_Covers' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpCovers::class,
                'SP_DistanceSphere' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpDistanceSphere::class,
                'SP_DWithin' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpDWithin::class,
                'SP_Expand' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpExpand::class,
                'SP_GeogFromText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpGeogFromText::class,
                'SP_GeographyFromText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpGeographyFromText::class,
                'SP_GeometryType' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpGeometryType::class,
                'SP_GeomFromEwkt' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpGeomFromEwkt::class,
                'SP_LineCrossingDirection' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpLineCrossingDirection::class,
                'SP_LineInterpolatePoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpLineInterpolatePoint::class,
                'SP_LineLocatePoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpLineLocatePoint::class,
                'SP_LineSubstring' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpLineSubstring::class,
                'SP_MakeBox2D' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpMakeBox2D::class,
                'SP_MakeEnvelope' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpMakeEnvelope::class,
                'SP_MakeLine' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpMakeLine::class,
                'SP_MakePoint' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpMakePoint::class,
                'SP_NPoints' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpNPoints::class,
                'SP_Scale' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpScale::class,
                'SP_Simplify' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpSimplify::class,
                'SP_SnapToGrid' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpSnapToGrid::class,
                'SP_Split' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpSplit::class,
                'SP_Summary' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpSummary::class,
                'SP_Transform' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpTransform::class,
                'SP_Translate' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpTranslate::class,
                */

                // Functions used by this module.
                // Some extra postgre params may not be used.
                // Note: Postgre uses "ST_DistanceSphere" and MySql "ST_Distance_Sphere".
                'ST_Buffer' => \DataTypeGeometry\Doctrine\ORM\Query\AST\Functions\StBuffer::class,
                'ST_Contains' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StContains::class,
                'ST_Distance' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StDistance::class,
                'ST_DistanceSphere' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\PostgreSql\SpDistanceSphere::class,
                'ST_Distance_Sphere' => \DataTypeGeometry\Doctrine\ORM\Query\AST\Functions\MySql\SpDistanceSphere::class,
                'ST_GeomFromText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeomFromText::class,
                'ST_Intersects' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StIntersects::class,
                'ST_MbrContains' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrContains::class,
                'ST_Point' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPoint::class,
                'ST_Srid' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StSrid::class,
                'ST_X' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StX::class,
                'ST_Y' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StY::class,

                // Aliases.
                // TODO Are aliases for doctrine spatial dql queries still useful? Remove old specific fixes.
                'MbrContains' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\MySql\SpMbrContains::class,
                // Point in mySql, ST_Point in Postgre, so use only st_geomfromtext.
                'Point' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StPoint::class,
            ],
            'string' => [
                'ST_AsText' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StAsText::class,
                'ST_AsWkt' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StAsText::class,
                'ST_GeometryType' => \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StGeometryType::class,
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
