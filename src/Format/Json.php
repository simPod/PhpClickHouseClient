<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/** @implements Format<\SimPod\ClickHouseClient\Output\Json> */
final class Json implements Format
{
    public static function output(string $contents) : Output
    {
        return new \SimPod\ClickHouseClient\Output\Json($contents);
    }

    public static function toSql() : string
    {
        return 'FORMAT JSON';
    }
}
