<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

/**
 * @template T
 * @implements Format<\SimPod\ClickHouseClient\Output\Null_<T>>
 */
final class Null_ implements Format
{
    public static function output(string $contents): Output
    {
        /** @var \SimPod\ClickHouseClient\Output\Null_<T> $output */
        $output = new \SimPod\ClickHouseClient\Output\Null_($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT Null';
    }
}
