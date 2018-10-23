<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Output;

/** @psalm-template-covariant O of Output */
interface Format
{
    public static function toSql() : string;

    /** @psalm-return O */
    public function output(string $contents) : Output;
}
