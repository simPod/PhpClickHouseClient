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
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(RequestFactory::class)]
#[CoversClass(PsrClickHouseClient::class)]
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
        $client = self::$client;
        $output = $client->selectWithParams('SELECT {p1:UInt8} AS data', ['p1' => 3], new TabSeparated());

        self::assertSame("3\n", $output->contents);
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

        yield [
            [
                ['number' => '0'],
                ['number' => '1'],
            ],
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

        yield [
            [
                ['0'],
                ['1'],
            ],
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

        self::assertSame($expectedData, $output->data);
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

        yield [
            [
                ['number' => '0'],
                ['number' => '1'],
            ],
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

    public function testSettingsArePassed(): void
    {
        self::expectException(ServerError::class);
        $this->expectExceptionMessageMatches("~DB::Exception: Database `non-existent` (doesn't|does not) exist~");

        self::$client->select('SELECT 1', new JsonCompact(), ['database' => 'non-existent']);
    }
}
