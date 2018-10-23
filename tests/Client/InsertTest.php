<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonCompact;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

final class InsertTest extends TestCaseBase
{
    use WithClient;

    /** @dataProvider providerInsert */
    public function testInsert(string $tableSql) : void
    {
        $data = [
            ['PageViews' => 5, 'UserID' => 4324182021466249494, 'Duration' => 146, 'Sign' => -1],
            ['PageViews' => 6, 'UserID' => 4324182021466249494, 'Duration' => 185, 'Sign' => 1],
        ];

        $this->client->executeQuery($tableSql);

        $this->client->insert('UserActivity', $data);

        $output = $this->client->select(
            <<<CLICKHOUSE
SELECT * FROM UserActivity
CLICKHOUSE
            ,
            new JsonEachRow()
        );

        $data[0]['UserID'] = (string) $data[0]['UserID'];
        $data[1]['UserID'] = (string) $data[1]['UserID'];
        self::assertSame($data, $output->data);
    }

    /** @dataProvider providerInsert */
    public function testInsertUseColumns(string $tableSql) : void
    {
        $expectedData = [
            ['PageViews' => 5, 'UserID' => '4324182021466249494', 'Duration' => 146, 'Sign' => -1],
            ['PageViews' => 6, 'UserID' => '4324182021466249494', 'Duration' => 185, 'Sign' => 1],
        ];

        $this->client->executeQuery($tableSql);

        $this->client->insert(
            'UserActivity',
            [
                [5, 4324182021466249494, 146, -1],
                [6, 4324182021466249494, 185, 1],
            ],
            ['PageViews', 'UserID', 'Duration', 'Sign']
        );

        $output = $this->client->select(
            <<<CLICKHOUSE
SELECT * FROM UserActivity
CLICKHOUSE
            ,
            new JsonEachRow()
        );

        self::assertSame($expectedData, $output->data);
    }

    public function testInsertEscaping() : void
    {
        $this->client->executeQuery(
            <<<CLICKHOUSE
CREATE TABLE a (
    b  Nullable(String)
)
ENGINE Memory
CLICKHOUSE
        );

        $expectedData = [
            [null],
            ["\t"],
        ];

        $this->client->insert('a', $expectedData);

        $output = $this->client->select(
            <<<CLICKHOUSE
SELECT * FROM a
CLICKHOUSE
            ,
            new JsonCompact()
        );

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array<string>> */
    public function providerInsert() : iterable
    {
        $sql = <<<CLICKHOUSE
CREATE TABLE UserActivity (
    PageViews   UInt32,
    UserID      UInt64,
    Duration    UInt32,
    Sign        Int8
)
ENGINE Memory
CLICKHOUSE;

        yield [$sql];
    }

    public function testInsertWithFormat() : void
    {
        $this->client->executeQuery(
            <<<CLICKHOUSE
CREATE TABLE UserActivity (
    PageViews   UInt32,
    UserID      UInt64,
    Duration    UInt32,
    Sign        Int8
)
ENGINE Memory
CLICKHOUSE
        );

        $this->client->insertWithFormat(
            'UserActivity',
            new JsonEachRow(),
            <<<JSONEACHROW
{"PageViews":5, "UserID":"4324182021466249494", "Duration":146,"Sign":-1} 
{"UserID":"4324182021466249494","PageViews":6,"Duration":185,"Sign":1}
JSONEACHROW
        );

        $output = $this->client->select(
            <<<CLICKHOUSE
SELECT * FROM UserActivity
CLICKHOUSE
            ,
            new JsonEachRow()
        );

        self::assertSame(
            [
                ['PageViews' => 5, 'UserID' => '4324182021466249494', 'Duration' => 146, 'Sign' => -1],
                ['PageViews' => 6, 'UserID' => '4324182021466249494', 'Duration' => 185, 'Sign' => 1],
            ],
            $output->data
        );
    }

    public function testInsertEmptyValuesThrowsException() : void
    {
        $this->expectException(CannotInsert::class);

        $this->client->insert('table', []);
    }

    public function testInsertToNonExistentTableExpectServerError() : void
    {
        $this->expectException(ServerError::class);

        $this->client->insert('table', [[1]]);
    }
}
