<?php
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * ST_GeomFromText DQL function
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STGeomFromText extends AbstractSpatialDQLFunction
{
    protected $platforms = [
        'mysql',
        'postgresql',
    ];

    protected $functionName = 'ST_GeomFromText';

    protected $minGeomExpr = 1;

    protected $maxGeomExpr = 2;
}
