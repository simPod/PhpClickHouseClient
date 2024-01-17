<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Param;

use Closure;
use DateTimeInterface;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Sql\Type;

use function array_keys;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

/** @phpstan-type Converter = Closure(mixed, Type|string|null, bool):(StreamInterface|string) */
final class ParamValueConverterRegistry
{
    /** @var list<string> */
    private static array $caseInsensitiveTypes = [
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
        'object',
        'json',
    ];

    /** @phpstan-var array<string, Converter> */
    private array $registry;

    public function __construct()
    {
        $formatPoint   = static fn (array $point) => sprintf('(%s)', implode(',', $point));
        $formatRing    = static fn (array $v) => sprintf('[%s]', implode(
            ',',
            array_map($formatPoint, $v),
        ));
        $formatPolygon = static fn (array $v) => sprintf('[%s]', implode(
            ',',
            array_map($formatRing, $v),
        ));

        /** @phpstan-var array<string, Converter> $registry */
        $registry       = [
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
            'datetime64' => static fn (DateTimeInterface|string|int|float $value) => $value instanceof DateTimeInterface
                ? $value->format('Y-m-d H:i:s.u')
                : $value,

            'IPv4' => self::noopConverter(),
            'IPv6' => self::noopConverter(),

            'enum' => self::noopConverter(),
            'Enum8' => self::noopConverter(),
            'Enum16' => self::noopConverter(),
            'Enum32' => self::noopConverter(),
            'Enum64' => self::noopConverter(),

            'json' => static fn (array|string $value) => is_string($value) ? $value : json_encode($value),
            'object' => fn (mixed $v, Type $type) => $this->get(trim($type->params, "'"))($v, $type, true),
            'Map' => self::noopConverter(),
            'Nested' => function (array|string $v, Type $type) {
                if (is_string($v)) {
                    return $v;
                }

                $types = array_map(static fn ($type) => explode(' ', trim($type))[1], explode(',', $type->params));

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
                    : $formatRing($v),
            'Polygon' => static fn (string|array $v) => is_string($v)
                    ? $v
                    : $formatPolygon($v),
            'MultiPolygon' => static fn (string|array $v) => is_string($v)
                ? $v
                : (static fn (array $vv) => sprintf('[%s]', implode(
                    ',',
                    array_map($formatPolygon, $vv),
                )))($v),

            'Array' => fn (array|string $v, Type $type) => is_string($v)
                ? $v
                : sprintf('[%s]', implode(
                    ',',
                    array_map(fn (mixed $v) => $this->get($type->params)($v, $type, true), $v),
                )),
            'Tuple' => function (array|string $v, Type $type) {
                if (is_string($v)) {
                    return $v;
                }

                $types = array_map(static fn ($p) => trim($p), explode(',', $type->params));

                return '(' . implode(
                    ',',
                    array_map(fn (mixed $i) => $this->get($types[$i])($v[$i], null, true), array_keys($v)),
                ) . ')';
            },
        ];
        $this->registry = $registry;
    }

    /**
     * @phpstan-return Converter
     *
     * @throws UnsupportedParamType;
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
        if ($converter !== null && in_array($typeName, self::$caseInsensitiveTypes, true)) {
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
        ) => $nested ? '\'' . str_replace("'", "\'", $value) . '\'' : $value;
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
        return static fn (DateTimeInterface|string|float $value) => $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }

    private static function dateTimeConverter(): Closure
    {
        return static fn (DateTimeInterface|string|int|float $value) => $value instanceof DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : $value;
    }

    private static function dateIntervalConverter(): Closure
    {
        return static fn (int|float $v) => $v;
    }
}
