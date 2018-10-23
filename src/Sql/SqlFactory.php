<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function str_replace;

final class SqlFactory
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function createWithParameters(string $sql, array $parameters) : string
    {
        foreach ($parameters as $name => $value) {
            $sql = str_replace(':' . $name, ValueFormatter::format($value), $sql);
        }

        return $sql;
    }
}
