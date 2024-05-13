<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;

use function array_map;
use function sprintf;

final readonly class ExpressionFactory
{
    public function __construct(private ValueFormatter $valueFormatter)
    {
    }

    /** @throws UnsupportedParamValue */
    public function templateAndValues(string $template, mixed ...$values): Expression
    {
        return Expression::new(
            sprintf($template, ...array_map([$this->valueFormatter, 'format'], $values)),
        );
    }
}
