<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Param;

use Closure;
use DateTimeInterface;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Sql\Escaper;
use SimPod\ClickHouseClient\Sql\Type;

use function array_keys;
use function array_map;
use function array_merge;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

/**
 * @phpstan-type Converter Closure(mixed, Type|string|null, bool):(StreamInterface|string)
 * @phpstan-type ConverterRegistry array<string, Converter>
 */
final class ParamValueConverterRegistry
{
    private const CaseInsensitiveTypes = [
        'bool',
        'date',
        'date32',
        'datetime',
        'datetime32',
        'datetime64',
        'decimal',
        'decimal32',
        'decimal64',
        'decimal128',
        'decimal256',
        'enum',
        'json',
    ];

    /** @phpstan-var ConverterRegistry */
    private array $registry;

    /** @phpstan-param ConverterRegistry $registry */
    public function __construct(array $registry = [])
    {
        $formatPoint = static fn (array $point) => sprintf('(%s)', implode(',', $point));
        // phpcs:ignore SlevomatCodingStandard.Functions.RequireArrowFunction.RequiredArrowFunction
        $formatRingOrLineString = static function (array $v) use ($formatPoint) {
            /** @phpstan-var array<array<string>> $v */
            return sprintf('[%s]', implode(
                ',',
                array_map($formatPoint, $v),
            ));
        };
        // phpcs:ignore SlevomatCodingStandard.Functions.RequireArrowFunction.RequiredArrowFunction
        $formatPolygonOrMultiLineString = static function (array $v) use ($formatRingOrLineString) {
            /** @phpstan-var array<array<string>> $v */
            return sprintf('[%s]', implode(
                ',',
                array_map($formatRingOrLineString, $v),
            ));
        };

        /** @phpstan-var ConverterRegistry $defaultRegistry */
        $defaultRegistry = [
            'String' => self::stringConverter(),
            'FixedString' => self::stringConverter(),

            'UUID' => self::stringConverter(),

            'Nullable' => fn (mixed $v, Type $type) => $this->get($type->params)($v, null, false),
            'LowCardinality' => fn (mixed $v, Type $type) => $this->get($type->params)($v, null, false),

            'decimal' => self::decimalConverter(),
            'decimal32' => self::decimalConverter(),
            'decimal64' => self::decimalConverter(),
            'decimal128' => self::decimalConverter(),
            'decimal256' => self::decimalConverter(),

            'bool' => static fn (bool $value) => $value,

            'date' => self::dateConverter(),
            'date32' => self::dateConverter(),
            'datetime' => self::dateTimeConverter(),
            'datetime32' => self::dateTimeConverter(),
            'datetime64' => static function (mixed $value) {
                if ($value instanceof DateTimeInterface) {
                    return $value->format('U.u');
                }

                if (is_string($value) || is_float($value) || is_int($value)) {
                    return $value;
                }

                throw UnsupportedParamValue::type($value);
            },

            'Dynamic' => self::noopConverter(),
            'Variant' => self::noopConverter(),

            'IPv4' => self::noopConverter(),
            'IPv6' => self::noopConverter(),

            'enum' => self::noopConverter(),
            'Enum8' => self::noopConverter(),
            'Enum16' => self::noopConverter(),
            'Enum32' => self::noopConverter(),
            'Enum64' => self::noopConverter(),

            'json' => static fn (array|string $value) => is_string($value) ? $value : json_encode($value),
            'Map' => self::noopConverter(),
            'Nested' => function (array|string $v, Type $type) {
                if (is_string($v)) {
                    return $v;
                }

                $types = array_map(static fn ($type) => explode(' ', trim($type))[1], $this->splitTypes($type->params));

                /** @phpstan-var array<array<string>> $v */
                return sprintf('[%s]', implode(',', array_map(
                    fn (array $row) => sprintf('(%s)', implode(',', array_map(
                        fn (int|string $i) => $this->get($types[$i])($row[$i], $types[$i], true),
                        array_keys($row),
                    ))),
                    $v,
                )));
            },

            'Float32' => self::floatConverter(),
            'Float64' => self::floatConverter(),

            'Int8' => self::intConverter(),
            'Int16' => self::intConverter(),
            'Int32' => self::intConverter(),
            'Int64' => self::intConverter(),
            'Int128' => self::intConverter(),
            'Int256' => self::intConverter(),

            'UInt8' => self::intConverter(),
            'UInt16' => self::intConverter(),
            'UInt32' => self::intConverter(),
            'UInt64' => self::intConverter(),
            'UInt128' => self::intConverter(),
            'UInt256' => self::intConverter(),

            'IntervalNanosecond' => self::dateIntervalConverter(),
            'IntervalMicrosecond' => self::dateIntervalConverter(),
            'IntervalMillisecond' => self::dateIntervalConverter(),
            'IntervalSecond' => self::dateIntervalConverter(),
            'IntervalMinute' => self::dateIntervalConverter(),
            'IntervalHour' => self::dateIntervalConverter(),
            'IntervalDay' => self::dateIntervalConverter(),
            'IntervalWeek' => self::dateIntervalConverter(),
            'IntervalMonth' => self::dateIntervalConverter(),
            'IntervalQuarter' => self::dateIntervalConverter(),
            'IntervalYear' => self::dateIntervalConverter(),

            'Point' => static fn (string|array $v) => is_string($v)
                ? $v
                : $formatPoint($v),
            'Ring' => static fn (string|array $v) => is_string($v)
                    ? $v
                    : $formatRingOrLineString($v),
            'LineString' => static fn (string|array $v) => is_string($v)
                    ? $v
                    : $formatRingOrLineString($v),
            'MultiLineString' => static fn (string|array $v) => is_string($v)
                    ? $v
                    : $formatPolygonOrMultiLineString($v),
            'Polygon' => static fn (string|array $v) => is_string($v)
                    ? $v
                    : $formatPolygonOrMultiLineString($v),
            'MultiPolygon' => static fn (string|array $v) => is_string($v)
                ? $v
                // phpcs:ignore SlevomatCodingStandard.Functions.RequireArrowFunction.RequiredArrowFunction
                : (static function (array $vv) use ($formatPolygonOrMultiLineString) {
                    /** @phpstan-var array<array<string>> $vv */
                    return sprintf('[%s]', implode(
                        ',',
                        array_map($formatPolygonOrMultiLineString, $vv),
                    ));
                })($v),

            'Array' => fn (array|string $v, Type $type) => is_string($v)
                ? $v
                : sprintf('[%s]', implode(
                    ',',
                    array_map(function (mixed $v) use ($type) {
                        $innerType = Type::fromString($type->params);

                        return $this->get($innerType)($v, $innerType, true);
                    }, $v),
                )),
            'Tuple' => function (mixed $v, Type $type) {
                if (! is_array($v)) {
                    return $v;
                }

                $innerTypes = $this->splitTypes($type->params);

                $innerExpression = implode(
                    ',',
                    array_map(function (int $i) use ($innerTypes, $v) {
                        $innerType = Type::fromString($innerTypes[$i]);

                        return $this->get($innerType)($v[$i], $innerType, true);
                    }, array_keys($v)),
                );

                return '(' . $innerExpression . ')';
            },
        ];
        $this->registry  = array_merge($defaultRegistry, $registry);
    }

    /**
     * @phpstan-return Converter
     *
     * @throws UnsupportedParamType
     */
    public function get(Type|string $type): Closure
    {
        $typeName = is_string($type) ? $type : $type->name;

        $converter = $this->registry[$typeName] ?? null;
        if ($converter !== null) {
            return $converter;
        }

        $typeName  = strtolower($typeName);
        $converter = $this->registry[$typeName] ?? null;
        if ($converter !== null && in_array($typeName, self::CaseInsensitiveTypes, true)) {
            return $converter;
        }

        return throw is_string($type)
            ? UnsupportedParamType::fromString($type) : UnsupportedParamType::fromType($type);
    }

    private static function stringConverter(): Closure
    {
        return static fn (
            string $value,
            Type|string|null $type = null,
            bool $nested = false,
        ) => $nested ? "'" . Escaper::escape($value) . "'" : $value;
    }

    private static function noopConverter(): Closure
    {
        return static fn (mixed $value) => $value;
    }

    private static function floatConverter(): Closure
    {
        return static fn (float|string $value) => $value;
    }

    private static function intConverter(): Closure
    {
        return static fn (int|string $value) => $value;
    }

    private static function decimalConverter(): Closure
    {
        return static fn (float|int|string $value) => $value;
    }

    private static function dateConverter(): Closure
    {
        return static function (mixed $value) {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_string($value) || is_float($value) || is_int($value)) {
                return $value;
            }

            throw UnsupportedParamValue::type($value);
        };
    }

    private static function dateTimeConverter(): Closure
    {
        return static function (mixed $value) {
            if ($value instanceof DateTimeInterface) {
                return $value->getTimestamp();
            }

            if (is_string($value) || is_float($value) || is_int($value)) {
                return $value;
            }

            throw UnsupportedParamValue::type($value);
        };
    }

    private static function dateIntervalConverter(): Closure
    {
        return static fn (int|float $v) => $v;
    }

    /** @return list<string> */
    private function splitTypes(string $types): array
    {
        $result  = [];
        $depth   = 0;
        $current = '';

        for ($i = 0; $i < strlen($types); $i++) {
            $char = $types[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $current  = trim($current);
                $result[] = $current;
                $current  = '';

                continue;
            }

            $current .= $char;
        }

        $current = trim($current);

        if ($current !== '') {
            $result[] = $current;
        }

        return $result;
    }
}
