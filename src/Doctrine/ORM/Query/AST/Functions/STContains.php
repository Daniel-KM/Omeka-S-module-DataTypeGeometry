<?php
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * Class STContains
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STContains extends AbstractSpatialDQLFunction
{
    protected $platforms = [
        'mysql',
        'postgresql',
    ];

    protected $functionName = 'ST_Contains';

    protected $minGeomExpr = 2;

    protected $maxGeomExpr = 2;
}
