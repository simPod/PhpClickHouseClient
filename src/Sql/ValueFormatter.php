<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use BackedEnum;
use DateTimeInterface;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;

use function array_key_first;
use function array_map;
use function assert;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function sprintf;

/** @internal */
final readonly class ValueFormatter
{
    /** @throws UnsupportedParamValue */
    public function format(mixed $value, string|null $paramName = null, string|null $sql = null): string
    {
        if (is_string($value)) {
            return "'" . Escaper::escape($value) . "'";
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            if (
                $paramName !== null && $sql !== null
                && preg_match(sprintf('~(HAVING|WHERE)[\s\S]*?=\s*?:%s~', $paramName), $sql) === 1
            ) {
                return 'IS NULL';
            }

            return 'NULL';
        }

        if ($value instanceof BackedEnum) {
            return is_string($value->value)
                ? "'" . Escaper::escape($value->value) . "'"
                : (string) $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return (string) $value->getTimestamp();
        }

        if ($value instanceof Expression) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return "'" . Escaper::escape((string) $value) . "'";
        }

        if (is_array($value)) {
            if (
                $paramName !== null && $sql !== null
                && preg_match(sprintf('~\s+?IN\s+?\\(:%s\\)~', $paramName), $sql) === 1
            ) {
                if ($value === []) {
                    throw UnsupportedParamValue::value($value);
                }

                $firstValue = $value[array_key_first($value)];
                $mapper     = is_array($firstValue)
                    ? function ($value): string {
                        assert(is_array($value));

                        return sprintf(
                            '(%s)',
                            implode(
                                ',',
                                array_map(fn ($val) => $this->format($val), $value),
                            ),
                        );
                    }
                    : fn ($value): string => $value === null ? 'NULL' : $this->format($value);

                return implode(
                    ',',
                    array_map($mapper, $value),
                );
            }

            return $this->formatArray($value);
        }

        throw UnsupportedParamValue::type($value);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string>
     *
     * @throws UnsupportedParamValue
     */
    public function mapFormat(array $values): array
    {
        return array_map(
            function ($value): string {
                if ($value === null) {
                    return 'NULL';
                }

                return $this->format($value);
            },
            $values,
        );
    }

    /**
     * @param array<mixed> $value
     *
     * @throws UnsupportedParamValue
     */
    private function formatArray(array $value): string
    {
        return sprintf(
            '[%s]',
            implode(
                ',',
                array_map(
                    function ($value): string {
                        if ($value === null) {
                            return 'NULL';
                        }

                        if (is_array($value)) {
                            return $this->formatArray($value);
                        }

                        return $this->format($value);
                    },
                    $value,
                ),
            ),
        );
    }
}
