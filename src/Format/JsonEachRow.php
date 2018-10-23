<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/**
 * @phpstan-implements Format<\SimPod\ClickHouseClient\Output\JsonEachRow>
 */
final class JsonEachRow implements Format
{
    public static function toSql() : string
    {
        return 'FORMAT JSONEachRow';
    }

    public static function output(string $contents) : Output
    {
        return new \SimPod\ClickHouseClient\Output\JsonEachRow($contents);
    }
}
