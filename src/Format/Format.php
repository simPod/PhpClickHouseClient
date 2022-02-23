<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/** @template-covariant O of Output */
interface Format
{
    /** @return O */
    public static function output(string $contents): Output;

    public static function toSql(): string;
}
