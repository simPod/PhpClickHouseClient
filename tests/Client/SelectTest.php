<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Format\JsonCompact;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Format\Null_;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;
use SimPod\ClickHouseClient\Tests\ClickHouseVersion;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function bin2hex;
use function implode;
use function iterator_to_array;
use function sprintf;
use function strlen;
use function strtoupper;

#[CoversClass(RequestFactory::class)]
#[CoversClass(PsrClickHouseClient::class)]
#[CoversClass(ParamValueConverterRegistry::class)]
#[CoversClass(ServerError::class)]
#[CoversClass(Json::class)]
#[CoversClass(\SimPod\ClickHouseClient\Output\Json::class)]
#[CoversClass(JsonEachRow::class)]
#[CoversClass(\SimPod\ClickHouseClient\Output\JsonEachRow::class)]
#[CoversClass(JsonCompact::class)]
#[CoversClass(\SimPod\ClickHouseClient\Output\JsonCompact::class)]
#[CoversClass(Null_::class)]
#[CoversClass(\SimPod\ClickHouseClient\Output\Null_::class)]
final class SelectTest extends TestCaseBase
{
    use WithClient;

    public function testSelectWithParams(): void
    {
        $value  = "actual\n\t\r\0\x08\x0C literal\\n \\N \\x41 'quoted' `identifier` trailing\\";
        $hex    = strtoupper(bin2hex($value));
        $sql    = sprintf(
            <<<'CLICKHOUSE'
SELECT
    {number:UInt8},
    hex({string:String}),
    hex({fixed:FixedString(%d)}),
    hex({nullable:Nullable(String)}),
    hex({low_cardinality:LowCardinality(String)}),
    hex({nested:Array(Tuple(String, UInt8))}[1].1),
    hex({literal_null:Nullable(String)}),
    hex(:legacy)
CLICKHOUSE,
            strlen($value),
        );
        $output = self::$client->selectWithParams(
            $sql,
            [
                'number' => 3,
                'string' => $value,
                'fixed' => $value,
                'nullable' => $value,
                'low_cardinality' => $value,
                'nested' => [[$value, 1]],
                'literal_null' => '\\N',
                'legacy' => $value,
            ],
            new TabSeparated(),
        );

        self::assertSame(
            implode("\t", ['3', $hex, $hex, $hex, $hex, $hex, '5C4E', $hex]) . "\n",
            $output->contents,
        );
    }

    #[DataProvider('providerJson')]
    public function testJson(mixed $expectedData, string $sql): void
    {
        $client = self::$client;
        $output = $client->select($sql, new Json());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public static function providerJson(): iterable
    {
        yield [
            [[1 => 1]],
            <<<'CLICKHOUSE'
SELECT 1
CLICKHOUSE,
        ];

        $number = ClickHouseVersion::quotes64BitIntegersInJson()
            ? [['number' => '0'], ['number' => '1']]
            : [['number' => 0], ['number' => 1]];

        yield [
            $number,
            <<<'CLICKHOUSE'
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ["'ping'" => 'ping'],
            ],
            <<<'CLICKHOUSE'
SELECT 'ping'
CLICKHOUSE,
        ];
    }

    #[DataProvider('providerJsonCompact')]
    public function testJsonCompact(mixed $expectedData, string $sql): void
    {
        $client = self::$client;
        $output = $client->select($sql, new JsonCompact());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public static function providerJsonCompact(): iterable
    {
        yield [
            [[1]],
            <<<'CLICKHOUSE'
SELECT 1
CLICKHOUSE,
        ];

        $number = ClickHouseVersion::quotes64BitIntegersInJson()
            ? [['0'], ['1']]
            : [[0], [1]];

        yield [
            $number,
            <<<'CLICKHOUSE'
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ['ping'],
            ],
            <<<'CLICKHOUSE'
SELECT 'ping'
CLICKHOUSE,
        ];
    }

    #[DataProvider('providerJsonEachRow')]
    public function testJsonEachRow(mixed $expectedData, string $sql): void
    {
        $output = self::$client->select($sql, new JsonEachRow());

        self::assertSame($expectedData, iterator_to_array($output->data, preserve_keys: false));
    }

    /** @return iterable<int, array{mixed, string}> */
    public static function providerJsonEachRow(): iterable
    {
        yield [
            [[1 => 1]],
            <<<'CLICKHOUSE'
SELECT 1
CLICKHOUSE,
        ];

        $number = ClickHouseVersion::quotes64BitIntegersInJson()
            ? [['number' => '0'], ['number' => '1']]
            : [['number' => 0], ['number' => 1]];

        yield [
            $number,
            <<<'CLICKHOUSE'
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ["'ping'" => 'ping'],
            ],
            <<<'CLICKHOUSE'
SELECT 'ping'
CLICKHOUSE,
        ];
    }

    public function testNull(): void
    {
        self::$client->select('SELECT 1', new Null_());

        self::assertTrue(true);
    }

    public function testSelectStream(): void
    {
        $stream = self::$client->selectStream('SELECT 1 AS data', new TabSeparated());

        self::assertSame("1\n", $stream->__toString());
    }

    public function testSelectStreamWithParams(): void
    {
        $stream = self::$client->selectStreamWithParams(
            'SELECT {p1:UInt8} AS data',
            ['p1' => 3],
            new TabSeparated(),
        );

        self::assertSame("3\n", $stream->__toString());
    }

    public function testSettingsArePassed(): void
    {
        self::expectException(ServerError::class);
        $this->expectExceptionMessageMatches("~DB::Exception: Database `non-existent` (doesn't|does not) exist~");

        self::$client->select('SELECT 1', new JsonCompact(), new ArraySettingsProvider(['database' => 'non-existent']));
    }
}
