<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/** @phpstan-implements Format<\SimPod\ClickHouseClient\Output\JsonCompact> */
final class JsonCompact implements Format
{
    public static function toSql() : string
    {
        return 'FORMAT JSONCompact';
    }

    public static function output(string $contents) : Output
    {
        return new \SimPod\ClickHouseClient\Output\JsonCompact($contents);
    }
}
