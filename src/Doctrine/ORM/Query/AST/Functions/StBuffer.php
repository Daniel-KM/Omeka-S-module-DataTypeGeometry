<?php declare(strict_types=1);

namespace DataTypeGeometry\Doctrine\ORM\Query\AST\Functions;

 class StBuffer extends \LongitudeOne\Spatial\ORM\Query\AST\Functions\Standard\StBuffer
{
    protected function getPlatforms(): array
    {
        return [
            'mysql',
            'postgresql',
        ];
    }
}
