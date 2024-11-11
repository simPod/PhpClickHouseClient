<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use SimPod\ClickHouseClient\Output\Basic;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<Basic<T>>
 */
final class TabSeparated implements Format
{
    public static function output(string $contents): Output
    {
        /** @var Basic<T> $output */
        $output = new Basic($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT TabSeparated';
    }
}
