<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Format;

use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Output\Output;

// phpcs:disable Squiz.Classes.ValidClassName.NotPascalCase

/**
 * @template T
 * @implements Format<\SimPod\ClickHouseClient\Output\Null_<T>>
 */
final readonly class Null_ implements Format
{
    public static function output(string|StreamInterface $contents): Output
    {
        $contents = $contents instanceof StreamInterface ? $contents->__toString() : $contents;

        /** @var \SimPod\ClickHouseClient\Output\Null_<T> $output */
        $output = new \SimPod\ClickHouseClient\Output\Null_($contents);

        return $output;
    }

    public static function toSql(): string
    {
        return 'FORMAT Null';
    }
}
