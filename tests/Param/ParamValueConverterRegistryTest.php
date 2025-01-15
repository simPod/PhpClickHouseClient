<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Param;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Tests\ClickHouseVersion;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function array_unique;
use function in_array;
use function sprintf;
use function strtolower;
use function trim;

#[CoversClass(ParamValueConverterRegistry::class)]
final class ParamValueConverterRegistryTest extends TestCaseBase
{
    use WithClient;

    private const VersionIntervalJson = 2301;

    /** @var array<string> */
    private static array $types = [];

    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    #[BeforeClass]
    public static function fetchAllTypes(): void
    {
        /** @var JsonEachRow<array{alias_to: string, case_insensitive: int, name: string}> $format */
        $format = new JsonEachRow();
        $rows   = self::$client->select(
            <<<'CLICKHOUSE'
            SELECT * FROM system.data_type_families
            CLICKHOUSE,
            $format,
        )->data;

        foreach ($rows as $row) {
            $type          = $row['alias_to'] === '' ? $row['name'] : $row['alias_to'];
            self::$types[] = $type;
            if ($row['case_insensitive'] !== 1 || $row['alias_to'] !== '') {
                continue;
            }

            self::$types[] = strtolower($type);
        }

        self::$types = array_unique(self::$types);
    }

    public function testAllTypesAreCovered(): void
    {
        self::assertNotEmpty(self::$types);

        $unsupportedTypeNames = [
            'AggregateFunction',
            'SimpleAggregateFunction',
            'Nothing',
            'Object',
        ];

        $registry = new ParamValueConverterRegistry();

        foreach (self::$types as $type) {
            if (in_array($type, $unsupportedTypeNames, true)) {
                continue;
            }

            $registry->get($type);

            $this->addToAssertionCount(1);
        }
    }

    #[DataProvider('providerConvert')]
    public function testConvert(string $type, mixed $value, mixed $expected): void
    {
        if (ClickHouseVersion::get() < 2206) {
            self::markTestSkipped();
        }

        self::assertSame(
            $expected,
            trim(
                self::$client->selectWithParams(
                    sprintf('SELECT {p1:%s}', $type),
                    ['p1' => $value],
                    new TabSeparated(),
                )->contents,
            ),
        );
    }

    /** @return Generator<array{string, mixed, mixed}> */
    public static function providerConvert(): Generator
    {
        yield 'Array' => ['Array(String)', "['foo','bar']", "['foo','bar']"];
        yield 'Array LC' => ['Array(LowCardinality(String))', "['foo','bar']", "['foo','bar']"];
        yield 'Array (array)' => ['Array(String)', ['foo', 'bar'], "['foo','bar']"];
        yield 'Array Tuple' => ['Array(Tuple(String, String))', [['foo', 'bar']], "[('foo','bar')]"];
        yield 'Array Tuple Complex' => [
            'Array(Tuple(Tuple(UInt32, UUID), String))',
            [[[1, '084caa96-915b-449d-8fc6-0292c73d6399'], 'bar']],
            "[((1,'084caa96-915b-449d-8fc6-0292c73d6399'),'bar')]",
        ];

        yield 'Tuple' => ['Tuple(String, Int8)', "('k',1)", "('k',1)"];
        yield 'Tuple (array)' => ['Tuple(String, Int8)', ['k', 1], "('k',1)"];
        yield 'Tuple (array complex)' => [
            'Tuple(Tuple(UInt32, String), UInt64, UInt8)',
            [[1, 'k'], 1 , 2],
            "((1,'k'),1,2)",
        ];

        if (ClickHouseVersion::get() >= self::VersionIntervalJson) {
            yield 'JSON' => ['JSON', '{"k":"v"}', '{"k":"v"}'];
            yield 'JSON (array)' => ['JSON', ['k' => 'v'], '{"k":"v"}'];
        }

        yield 'Map' => ['Map(String, UInt64)', "{'k1':1}", "{'k1':1}"];
        yield 'Nested' => [
            'Nested(id UUID, a String)',
            "[('084caa96-915b-449d-8fc6-0292c73d6399','1')]",
            "[('084caa96-915b-449d-8fc6-0292c73d6399','1')]",
        ];

        yield 'Nested (array)' => [
            'Nested(id UUID, a String)',
            [['084caa96-915b-449d-8fc6-0292c73d6399','1']],
            "[('084caa96-915b-449d-8fc6-0292c73d6399','1')]",
        ];

        yield 'String' => ['String', 'foo', 'foo'];
        yield 'FixedString' => ['FixedString(3)', 'foo', 'foo'];

        yield 'UUID' => ['UUID', 'de90cd12-7100-436e-bfb8-f77e4c7a224f', 'de90cd12-7100-436e-bfb8-f77e4c7a224f'];

        yield 'Date' => ['Date', '2023-02-01', '2023-02-01'];
        yield 'Date (datetime)' => ['Date', new DateTimeImmutable('2023-02-01'), '2023-02-01'];
        yield 'Date32' => ['Date32', new DateTimeImmutable('2023-02-01'), '2023-02-01'];
        yield 'DateTime' => ['DateTime', new DateTimeImmutable('2023-02-01 01:02:03'), '2023-02-01 01:02:03'];
        yield 'DateTime32' => ['DateTime32', new DateTimeImmutable('2023-02-01 01:02:03'), '2023-02-01 01:02:03'];
        yield 'DateTime64(3)' => [
            'DateTime64(3)',
            new DateTimeImmutable('2023-02-01 01:02:03.123456'),
            '2023-02-01 01:02:03.123',
        ];

        yield 'DateTime64(4)' => [
            'DateTime64(4)',
            new DateTimeImmutable('2023-02-01 01:02:03.123456'),
            '2023-02-01 01:02:03.1234',
        ];

        yield 'DateTime64(6)' => [
            'DateTime64(6)',
            new DateTimeImmutable('2023-02-01 01:02:03.123456'),
            '2023-02-01 01:02:03.123456',
        ];

        yield 'DateTime64(9)' => ['DateTime64(9)', 1675213323123456789, '2023-02-01 01:02:03.123456789'];
        yield 'DateTime64(9) (float)' => ['DateTime64(9)', 1675213323.1235, '2023-02-01 01:02:03.123500000'];
        yield 'DateTime64(9) (string)' => ['DateTime64(9)', '1675213323.123456789', '2023-02-01 01:02:03.123456789'];

        yield 'Bool' => ['Bool', true, 'true'];

        yield 'Variant' => ['Variant(String, Int8)', 'test', 'test'];

        yield 'Nullable' => ['Nullable(String)', 'foo', 'foo'];
        yield 'LowCardinality' => ['LowCardinality(String)', 'foo', 'foo'];

        yield 'Enum' => ["Enum('a' = 1, 'b' = 2)", 'a', 'a'];
        yield 'Enum8' => ["Enum8('a' = 1, 'b' = 2)", 'a', 'a'];
        yield 'Enum16' => ["Enum16('a' = 1, 'b' = 2)", 'a', 'a'];

        yield 'Int8' => ['Int8', 1, '1'];
        yield 'Int8 (string)' => ['Int8', '1', '1'];
        yield 'Int16' => ['Int16', 1, '1'];
        yield 'Int32' => ['Int32', 1, '1'];
        yield 'Int64' => ['Int64', 1, '1'];
        yield 'Int128' => ['Int128', 1, '1'];
        yield 'Int256' => ['Int256', 1, '1'];

        yield 'Float32' => ['Float32', 1.1, '1.1'];
        yield 'Float32 (string)' => ['Float32', '1.1', '1.1'];
        yield 'Float64' => ['Float64', 1.1, '1.1'];

        yield 'UInt8' => ['UInt8', 1, '1'];
        yield 'UInt8 (string)' => ['UInt8', '1', '1'];
        yield 'UInt16' => ['UInt16', 1, '1'];
        yield 'UInt32' => ['UInt32', 1, '1'];
        yield 'UInt64' => ['UInt64', 1, '1'];
        yield 'UInt128' => ['UInt128', 1, '1'];
        yield 'UInt256' => ['UInt256', 1, '1'];

        yield 'Decimal' => ['Decimal(10,0)', 3.33, '3'];
        yield 'Decimal (string)' => ['Decimal(10,0)', '3.33', '3'];

        yield 'Decimal32' => ['Decimal32(2)', 3.33, '3.33'];
        yield 'Decimal64' => ['Decimal64(2)', 3.33, '3.33'];
        yield 'Decimal128' => ['Decimal128(2)', 3.33, '3.33'];

        if (ClickHouseVersion::get() >= 2303) {
            yield 'Decimal256' => ['Decimal256(2)', 3.33, '3.33'];
        }

        if (ClickHouseVersion::get() >= self::VersionIntervalJson) {
            yield 'IntervalNanosecond' => ['IntervalNanosecond', 1, '1'];
            yield 'IntervalMicrosecond' => ['IntervalMicrosecond', 1, '1'];
            yield 'IntervalMillisecond' => ['IntervalMillisecond', 1, '1'];
            yield 'IntervalSecond' => ['IntervalSecond', 1, '1'];
            yield 'IntervalMinute' => ['IntervalMinute', 1, '1'];
            yield 'IntervalHour' => ['IntervalHour', 1, '1'];
            yield 'IntervalDay' => ['IntervalDay', 1, '1'];
            yield 'IntervalWeek' => ['IntervalWeek', 1, '1'];
            yield 'IntervalMonth' => ['IntervalMonth', 1, '1'];
            yield 'IntervalQuarter' => ['IntervalQuarter', 1, '1'];
            yield 'IntervalYear' => ['IntervalYear', 1, '1'];
        }

        yield 'Point' => ['Point', '(1,2)', '(1,2)'];
        yield 'Point (array)' => ['Point', [1, 2], '(1,2)'];
        yield 'Ring' => ['Ring', '[(1,2),(3,4)]', '[(1,2),(3,4)]'];
        yield 'Ring (array)' => ['Ring', [[1, 2], [3, 4]], '[(1,2),(3,4)]'];
        yield 'Polygon' => ['Polygon', '[[(1,2),(3,4)],[(5,6),(7,8)]]', '[[(1,2),(3,4)],[(5,6),(7,8)]]'];
        yield 'Polygon (array)' => ['Polygon', [[[1, 2], [3, 4]], [[5, 6], [7, 8]]], '[[(1,2),(3,4)],[(5,6),(7,8)]]'];
        yield 'MultiPolygon' => [
            'MultiPolygon',
            '[[[(1,2),(3,4)],[(5,6),(7,8)]],[[(9,8),(7,6)]]]',
            '[[[(1,2),(3,4)],[(5,6),(7,8)]],[[(9,8),(7,6)]]]',
        ];

        yield 'MultiPolygon (array)' => [
            'MultiPolygon',
            [
                [
                    [[1, 2], [3, 4]],
                    [[5, 6], [7, 8]],
                ],
                [
                    [[9,8], [7,6]],
                ],
            ],
            '[[[(1,2),(3,4)],[(5,6),(7,8)]],[[(9,8),(7,6)]]]',
        ];

        yield 'IPv4' => ['IPv4', '1.2.3.4', '1.2.3.4'];
        yield 'IPv6' => ['IPv6', '2001:0000:130F:0000:0000:09C0:876A:130B', '2001:0:130f::9c0:876a:130b'];
    }

    public function testThrowsOnUnknownType(): void
    {
        $registry = new ParamValueConverterRegistry();

        $this->expectException(UnsupportedParamType::class);
        $registry->get('fOo');
    }
}
