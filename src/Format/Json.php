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
        return new \SimPod\ClickHouseClient\Output\Json($contents);
    }

    public static function toSql(): string
    {
        return 'FORMAT JSON';
    }
}
