<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/**
 * @phpstan-implements Format<\SimPod\ClickHouseClient\Output\TabSeparated>
 */
final class TabSeparated implements Format
{
    public static function toSql() : string
    {
        return 'TabSeparated';
    }

    public static function output(string $contents) : Output
    {
        return new \SimPod\ClickHouseClient\Output\TabSeparated($contents);
    }
}
