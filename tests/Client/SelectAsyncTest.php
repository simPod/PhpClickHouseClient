<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use GuzzleHttp\Promise\Utils;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Format\TabSeparated;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/**
 * @covers \SimPod\ClickHouseClient\Client\Http\RequestFactory
 * @covers \SimPod\ClickHouseClient\Client\PsrClickHouseAsyncClient
 * @covers \SimPod\ClickHouseClient\Exception\ServerError
 * @covers \SimPod\ClickHouseClient\Format\JsonEachRow
 * @covers \SimPod\ClickHouseClient\Format\TabSeparated
 */
final class SelectAsyncTest extends TestCaseBase
{
    use WithClient;

    public function testAsyncSelect(): void
    {
        $client = $this->asyncClient;

        $sql = <<<'CLICKHOUSE'
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE;

        /** @var JsonEachRow<array{number: string}> $format */
        $format = new JsonEachRow();

        $promises = [
            $client->select($sql, $format),
            $client->select($sql, $format),
        ];

        $jsonEachRowOutputs = Utils::all($promises)->wait();

        $expectedData = [
            ['number' => '0'],
            ['number' => '1'],
        ];

        self::assertIsArray($jsonEachRowOutputs);
        self::assertCount(2, $jsonEachRowOutputs);
        self::assertSame($expectedData, $jsonEachRowOutputs[0]->data);
        self::assertSame($expectedData, $jsonEachRowOutputs[1]->data);
    }

    public function testSelectFromNonExistentTableExpectServerError(): void
    {
        $this->expectException(ServerError::class);

        $this->asyncClient->select('table', new TabSeparated())->wait();
    }
}
