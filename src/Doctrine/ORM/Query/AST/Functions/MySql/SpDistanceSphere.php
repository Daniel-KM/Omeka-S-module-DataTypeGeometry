<?php declare(strict_types=1);

namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions\MySql;

use LongitudeOne\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * ST_Distance_Sphere DQL function.
 */
class SpDistanceSphere extends AbstractSpatialDQLFunction
{
    /**
     * Function SQL name getter.
     *
     * @since 2.0 This function replace the protected property functionName.
     */
    protected function getFunctionName(): string
    {
        return 'ST_Distance_Sphere';
    }

    /**
     * Maximum number of parameter for the spatial function.
     *
     * @since 2.0 This function replace the protected property maxGeomExpr.
     *
     * @return int the inherited methods shall NOT return null, but 0 when function has no parameter
     */
    protected function getMaxParameter(): int
    {
        return 2;
    }

    /**
     * Minimum number of parameter for the spatial function.
     *
     * @since 2.0 This function replace the protected property minGeomExpr.
     *
     * @return int the inherited methods shall NOT return null, but 0 when function has no parameter
     */
    protected function getMinParameter(): int
    {
        return 2;
    }

    /**
     * Get the platforms accepted.
     *
     * @since 2.0 This function replace the protected property platforms.
     *
     * @return string[] a non-empty array of accepted platforms
     */
    protected function getPlatforms(): array
    {
        return [
            'mysql',
        ];
    }
}
