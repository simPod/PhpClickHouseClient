<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Format\TabSeparated;
use function GuzzleHttp\Promise\all;

final class SelectAsyncTest extends TestCaseBase
{
    use WithClient;

    public function testAsyncSelect() : void
    {
        $client = $this->asyncClient;

        $sql = <<<CLICKHOUSE
SELECT number FROM system.numbers LIMIT 2
CLICKHOUSE;

        $promises   = [];
        $promises[] = $client->select($sql, new JsonEachRow());
        $promises[] = $client->select($sql, new JsonEachRow());

        $jsonEachRowOutputs = all($promises)->wait();

        $expectedData = [
            ['number' => '0'],
            ['number' => '1'],
        ];

        self::assertCount(2, $jsonEachRowOutputs);
        self::assertSame($expectedData, $jsonEachRowOutputs[0]->data());
        self::assertSame($expectedData, $jsonEachRowOutputs[1]->data());
    }

    public function testSelectFromNonExistentTableExpectServerError() : void
    {
        $this->expectException(ServerError::class);

        $this->asyncClient->select('table', new TabSeparated())->wait();
    }
}
