<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function array_map;
use function Safe\sprintf;

final class ExpressionFactory
{
    /** @var ValueFormatter */
    private $valueFormatter;

    public function __construct(ValueFormatter $valueFormatter)
    {
        $this->valueFormatter = $valueFormatter;
    }

    /** @param mixed ...$values */
    public function templateAndValues(string $template, ...$values) : Expression
    {
        return Expression::new(
            sprintf($template, ...array_map([$this->valueFormatter, 'format'], $values))
        );
    }
}
