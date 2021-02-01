<?php declare(strict_types=1);
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * Class STY
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STY extends AbstractSpatialDQLFunction
{
    protected $platforms = [
        'mysql',
        'postgresql',
    ];

    protected $functionName = 'ST_Y';

    protected $minGeomExpr = 1;

    protected $maxGeomExpr = 1;
}
