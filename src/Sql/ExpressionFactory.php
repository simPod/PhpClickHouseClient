<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function array_map;
use function Safe\sprintf;

final class ExpressionFactory
{
    public function __construct(private ValueFormatter $valueFormatter)
    {
    }

    public function templateAndValues(string $template, mixed ...$values): Expression
    {
        return Expression::new(
            sprintf($template, ...array_map([$this->valueFormatter, 'format'], $values))
        );
    }
}
