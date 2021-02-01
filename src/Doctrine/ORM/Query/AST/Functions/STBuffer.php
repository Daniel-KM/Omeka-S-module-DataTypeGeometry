<?php declare(strict_types=1);
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * Class STBuffer
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STBuffer extends AbstractSpatialDQLFunction
{
    protected $platforms = [
        'mysql',
        'postgresql',
    ];

    protected $functionName = 'ST_Buffer';

    protected $minGeomExpr = 2;

    protected $maxGeomExpr = 3;
}
