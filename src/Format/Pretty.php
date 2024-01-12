<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Basic;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<Basic<T>>
 */
final class Pretty implements Format
{
    public static function output(string $contents): Output
    {
        return new Basic($contents);
    }

    public static function toSql(): string
    {
        return 'FORMAT Pretty';
    }
}
