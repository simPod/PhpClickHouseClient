<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use JsonException;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<\SimPod\ClickHouseClient\Output\JsonEachRow<T>>
 */
final class JsonEachRow implements Format
{
    /** @throws JsonException */
    public static function output(string $contents): Output
    {
        /** @var \SimPod\ClickHouseClient\Output\JsonEachRow<T> $output */
        $output = new \SimPod\ClickHouseClient\Output\JsonEachRow($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT JSONEachRow';
    }
}
