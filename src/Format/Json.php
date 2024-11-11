<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use JsonException;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<\SimPod\ClickHouseClient\Output\Json<T>>
 */
final class Json implements Format
{
    /** @throws JsonException */
    public static function output(string $contents): Output
    {
        /** @var \SimPod\ClickHouseClient\Output\Json<T> $output */
        $output = new \SimPod\ClickHouseClient\Output\Json($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT JSON';
    }
}
