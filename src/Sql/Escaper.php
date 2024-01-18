<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function str_replace;

/**
 * phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
 *
 * @link https://github.com/ClickHouse/clickhouse-jdbc/blob/8481c1323f5de09bb9dbbf67085e5e1b2585756a/src/main/java/ru/yandex/clickhouse/ClickHouseUtil.java
 */
final class Escaper
{
    public static function escape(string $s): string
    {
        return str_replace(
            // phpcs:disable SlevomatCodingStandard.Arrays.SingleLineArrayWhitespace.SpaceAfterComma
            // phpcs:ignore SlevomatCodingStandard.Arrays.SingleLineArrayWhitespace.SpaceBeforeArrayClose
            ['\\',   "\n",  "\t",  "\b",  "\f", "\r",  "\0",  "'",   '`'  ],
            ['\\\\', "\\n", "\\t", "\\b", "\f", "\\r", "\\0", "\\'", '\\`'],
            $s,
        );
    }

    public static function quoteIdentifier(string $s): string
    {
        return '`' . self::escape($s) . '`';
    }
}
