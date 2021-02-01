<?php declare(strict_types=1);
namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions\MySql;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * Class STDistanceSphere
 * @package CrEOF\Spatial\ORM\Query\AST\Functions
 */
class STDistanceSphere extends AbstractSpatialDQLFunction
{
    protected $platforms = ['mysql'];

    protected $functionName = 'ST_Distance_Sphere';

    protected $minGeomExpr = 2;

    protected $maxGeomExpr = 2;
}
