<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use DateTimeImmutable;
use DateTimeZone;
use SimPod\ClickHouseClient\Exception\UnsupportedValueType;
use function array_map;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function Safe\preg_match;
use function Safe\sprintf;

/** @internal */
final class ValueFormatter
{
    /** @var DateTimeZone|null */
    private $dateTimeZone;

    public function __construct(?DateTimeZone $dateTimeZone = null)
    {
        $this->dateTimeZone = $dateTimeZone;
    }

    /** @param mixed $value */
    public function format($value, ?string $paramName = null, ?string $sql = null) : string
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
            return 'IS NULL';
        }

        if ($value instanceof DateTimeImmutable) {
            if ($this->dateTimeZone !== null) {
                $value = $value->setTimezone($this->dateTimeZone);
            }

            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }

        if ($value instanceof Expression) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return "'" . $value . "'";
        }

        if (is_array($value)) {
            if ($paramName !== null && $sql !== null
                && preg_match(sprintf('~\s+IN\s+\\(:%s\\)~', $paramName), $sql) === 1
            ) {
                return implode(
                    ',',
                    array_map(
                        function ($value) : string {
                            if ($value === null) {
                                return 'NULL';
                            }

                            return $this->format($value);
                        },
                        $value
                    )
                );
            }

            return $this->formatArray($value);
        }

        throw UnsupportedValueType::value($value);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string>
     */
    public function mapFormat(array $values) : array
    {
        return array_map(
            function ($value) : string {
                if ($value === null) {
                    return 'NULL';
                }

                return $this->format($value);
            },
            $values
        );
    }

    /** @param array<mixed> $value */
    private function formatArray(array $value) : string
    {
        return sprintf(
            '[%s]',
            implode(
                ',',
                array_map(
                    function ($value) : string {
                        if ($value === null) {
                            return 'NULL';
                        }

                        if (is_array($value)) {
                            return $this->formatArray($value);
                        }

                        return $this->format($value);
                    },
                    $value
                )
            )
        );
    }
}
