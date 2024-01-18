<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonCompact;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(RequestFactory::class)]
#[CoversClass(PsrClickHouseClient::class)]
#[CoversClass(CannotInsert::class)]
#[CoversClass(ServerError::class)]
#[CoversClass(JsonEachRow::class)]
#[CoversClass(JsonCompact::class)]
final class InsertTest extends TestCaseBase
{
    use WithClient;

    #[DataProvider('providerInsert')]
    public function testInsert(string $tableSql): void
    {
        $data = [
            ['PageViews' => 5, 'UserID' => 4324182021466249494, 'Duration' => 146, 'Sign' => -1],
            ['PageViews' => 6, 'UserID' => 4324182021466249494, 'Duration' => 185, 'Sign' => 1],
        ];

        self::$client->executeQuery($tableSql);

        self::$client->insert('UserActivity', $data);

        $output = self::$client->select(
            <<<'CLICKHOUSE'
SELECT * FROM UserActivity
CLICKHOUSE,
            new JsonEachRow(),
        );

        $data[0]['UserID'] = (string) $data[0]['UserID'];
        $data[1]['UserID'] = (string) $data[1]['UserID'];
        self::assertSame($data, $output->data);
    }

    #[DataProvider('providerInsert')]
    public function testInsertUseColumns(string $tableSql): void
    {
        $expectedData = [
            ['PageViews' => 5, 'UserID' => '4324182021466249494', 'Duration' => 146, 'Sign' => -1],
            ['PageViews' => 6, 'UserID' => '4324182021466249494', 'Duration' => 185, 'Sign' => 1],
        ];

        self::$client->executeQuery($tableSql);

        self::$client->insert(
            'UserActivity',
            [
                [5, 4324182021466249494, 146, -1],
                [6, 4324182021466249494, 185, 1],
            ],
            ['PageViews', 'UserID', 'Duration', 'Sign'],
        );

        $output = self::$client->select(
            <<<'CLICKHOUSE'
SELECT * FROM UserActivity
CLICKHOUSE,
            new JsonEachRow(),
        );

        self::assertSame($expectedData, $output->data);
    }

    #[DataProvider('providerInsert')]
    public function testInsertUseColumnsWithTypes(string $tableSql): void
    {
        $expectedData = [
            ['PageViews' => 5, 'UserID' => '4324182021466249494', 'Duration' => 146, 'Sign' => -1],
            ['PageViews' => 6, 'UserID' => '4324182021466249494', 'Duration' => 185, 'Sign' => 1],
        ];

        self::$client->executeQuery($tableSql);

        self::$client->insert(
            'UserActivity',
            [
                [5, 4324182021466249494, 146, -1],
                [6, 4324182021466249494, 185, 1],
            ],
            ['PageViews' => 'UInt32', 'UserID' => 'UInt64', 'Duration' => 'UInt32', 'Sign' => 'Int8'],
        );

        $output = self::$client->select(
            <<<'CLICKHOUSE'
            SELECT * FROM UserActivity
            CLICKHOUSE,
            new JsonEachRow(),
        );

        self::assertSame($expectedData, $output->data);
    }

    public function testInsertEscaping(): void
    {
        self::$client->executeQuery(
            <<<'CLICKHOUSE'
CREATE TABLE a (
    b  Nullable(String)
)
ENGINE Memory
CLICKHOUSE,
        );

        $expectedData = [
            [null],
            ["\t"],
        ];

        self::$client->insert('a', $expectedData);

        $output = self::$client->select(
            <<<'CLICKHOUSE'
SELECT * FROM a
CLICKHOUSE,
            new JsonCompact(),
        );

        self::assertSame($expectedData, $output->data);
    }

    /** @return iterable<int, array<string>> */
    public static function providerInsert(): iterable
    {
        $sql = <<<'CLICKHOUSE'
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

    public function testInsertWithFormat(): void
    {
        self::$client->executeQuery(
            <<<'CLICKHOUSE'
CREATE TABLE UserActivity (
    PageViews   UInt32,
    UserID      UInt64,
    Duration    UInt32,
    Sign        Int8
)
ENGINE Memory
CLICKHOUSE,
        );

        self::$client->insertWithFormat(
            'UserActivity',
            new JsonEachRow(),
            <<<'JSONEACHROW'
{"PageViews":5, "UserID":"4324182021466249494", "Duration":146,"Sign":-1} 
{"UserID":"4324182021466249494","PageViews":6,"Duration":185,"Sign":1}
JSONEACHROW,
        );

        $output = self::$client->select(
            <<<'CLICKHOUSE'
SELECT * FROM UserActivity
CLICKHOUSE
            ,
            new JsonEachRow(),
        );

        self::assertSame(
            [
                ['PageViews' => 5, 'UserID' => '4324182021466249494', 'Duration' => 146, 'Sign' => -1],
                ['PageViews' => 6, 'UserID' => '4324182021466249494', 'Duration' => 185, 'Sign' => 1],
            ],
            $output->data,
        );
    }

    public function testInsertEmptyValuesThrowsException(): void
    {
        $this->expectException(CannotInsert::class);

        self::$client->insert('table', []);
    }

    public function testInsertToNonExistentTableExpectServerError(): void
    {
        $this->expectException(ServerError::class);

        self::$client->insert('table', [[1]]);
    }

    public function testInsertWithWrongColumns(): void
    {
        $tableSql = <<<'CLICKHOUSE'
            CREATE TABLE UserActivity (
                PageViews   UInt32,
                UserID      UInt64,
                Duration    UInt32,
                Sign        Int8
            )
            ENGINE Memory
            CLICKHOUSE;

        self::$client->executeQuery($tableSql);

        $this->expectException(ServerError::class);
        $this->expectExceptionMessage('SYNTAX_ERROR');

        self::$client->insert(
            'UserActivity',
            [
                [5],
                [6],
            ],
            ['PageViews', 'UserID'],
        );
    }
}
