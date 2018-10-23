<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function str_replace;

/** @internal */
final class SqlFactory
{
    /** @var ValueFormatter */
    private $valueFormatter;

    public function __construct(ValueFormatter $valueFormatter)
    {
        $this->valueFormatter = $valueFormatter;
    }

    /** @param array<string, mixed> $parameters */
    public function createWithParameters(string $query, array $parameters) : string
    {
        foreach ($parameters as $name => $value) {
            $query = str_replace(':' . $name, $this->valueFormatter->format($value, $name, $query), $query);
        }

        return $query;
    }
}
