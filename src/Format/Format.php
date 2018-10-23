<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/** @phpstan-template O of Output */
interface Format
{
    public static function toSql() : string;

    /** @phpstan-return O */
    public static function output(string $contents) : Output;
}
