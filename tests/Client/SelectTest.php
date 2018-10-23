<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Format\JsonCompact;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

final class SelectTest extends TestCaseBase
{
    use WithClient;

    /**
     * @param mixed $expectedData
     *
     * @dataProvider providerJson
     */
    public function testJson($expectedData, string $sql) : void
    {
        $client = $this->client;
        $output = $client->select($sql, new Json());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJson() : iterable
    {
        yield [
            [[1 => 1]],
            <<<CLICKHOUSE
SELECT 1
CLICKHOUSE,
        ];

        yield [
            [
                ['number' => '0'],
                ['number' => '1'],
            ],
            <<<CLICKHOUSE
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ["'ping'" => 'ping'],
            ],
            <<<CLICKHOUSE
SELECT 'ping'
CLICKHOUSE,
        ];
    }

    /**
     * @param mixed $expectedData
     *
     * @dataProvider providerJsonCompact
     */
    public function testJsonCompact($expectedData, string $sql) : void
    {
        $client = $this->client;
        $output = $client->select($sql, new JsonCompact());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJsonCompact() : iterable
    {
        yield [
            [[1]],
            <<<CLICKHOUSE
SELECT 1
CLICKHOUSE,
        ];

        yield [
            [
                ['0'],
                ['1'],
            ],
            <<<CLICKHOUSE
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ['ping'],
            ],
            <<<CLICKHOUSE
SELECT 'ping'
CLICKHOUSE,
        ];
    }

    /**
     * @param mixed $expectedData
     *
     * @dataProvider providerJsonEachRow
     */
    public function testJsonEachRow($expectedData, string $sql) : void
    {
        $client = $this->client;
        $output = $client->select($sql, new JsonEachRow());

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array{mixed, string}> */
    public function providerJsonEachRow() : iterable
    {
        yield [
            [[1 => 1]],
            <<<CLICKHOUSE
SELECT 1
CLICKHOUSE,
        ];

        yield [
            [
                ['number' => '0'],
                ['number' => '1'],
            ],
            <<<CLICKHOUSE
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE,
        ];

        yield [
            [
                ["'ping'" => 'ping'],
            ],
            <<<CLICKHOUSE
SELECT 'ping'
CLICKHOUSE,
        ];
    }
}
