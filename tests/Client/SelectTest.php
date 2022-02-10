<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Format\JsonCompact;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

final class SelectTest extends TestCaseBase
{
    use WithClient;

    /** @dataProvider providerJson */
    public function testJson(mixed $expectedData, string $sql): void
    {
        $client = $this->client;
        $output = $client->select($sql, new Json());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJson(): iterable
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

    /** @dataProvider providerJsonCompact */
    public function testJsonCompact(mixed $expectedData, string $sql): void
    {
        $client = $this->client;
        $output = $client->select($sql, new JsonCompact());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJsonCompact(): iterable
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

    /** @dataProvider providerJsonEachRow */
    public function testJsonEachRow(mixed $expectedData, string $sql): void
    {
        $client = $this->client;
        $output = $client->select($sql, new JsonEachRow());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJsonEachRow(): iterable
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

    public function testSettingsArePassed(): void
    {
        self::expectException(ServerError::class);
        $this->expectExceptionMessage("DB::Exception: Database `non-existent` doesn't exist");

        $this->client->select('SELECT 1', new JsonCompact(), ['database' => 'non-existent']);
    }
}
