<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use JsonException;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<\SimPod\ClickHouseClient\Output\JsonCompact<T>>
 */
final class JsonCompact implements Format
{
    /** @throws JsonException */
    public static function output(string $contents): Output
    {
        /** @var \SimPod\ClickHouseClient\Output\JsonCompact<T> $output */
        $output = new \SimPod\ClickHouseClient\Output\JsonCompact($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT JSONCompact';
    }
}
