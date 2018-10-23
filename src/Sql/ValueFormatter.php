<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use DateTimeInterface;
use SimPod\ClickHouseClient\Exception\UnsupportedValueType;
use function array_map;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;

final class ValueFormatter
{
    /**
     * @param mixed $value
     */
    public static function format($value, bool $inArray = false) : string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            if ($inArray) {
                return 'NULL';
            }

            return 'IS NULL';
        }

        if ($value instanceof DateTimeInterface) {
            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }

        if ($value instanceof Expression) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return "'" . $value . "'";
        }

        if (is_array($value)) {
            return implode(
                ',',
                array_map(
                    static function ($value) : string {
                        return ValueFormatter::format($value, true);
                    },
                    $value
                )
            );
        }

        throw UnsupportedValueType::value($value);
    }
}
