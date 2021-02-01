<?php declare(strict_types=1);
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * Class STDistance
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STDistance extends AbstractSpatialDQLFunction
{
    protected $platforms = [
        'mysql',
        'postgresql',
    ];

    protected $functionName = 'ST_Distance';

    protected $minGeomExpr = 2;

    protected $maxGeomExpr = 3;
}
