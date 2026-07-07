<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Output\Basic;
use SimPod\ClickHouseClient\Output\Output;

/**
 * @template T
 * @implements Format<Basic<T>>
 */
final readonly class TabSeparated implements Format
{
    public static function output(string|StreamInterface $contents): Output
    {
        $contents = $contents instanceof StreamInterface ? $contents->__toString() : $contents;

        /** @var Basic<T> $output */
        $output = new Basic($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT TabSeparated';
    }
}
