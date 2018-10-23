<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use SimPod\ClickHouseClient\Format\JsonEachRow;

final class InsertWithFormatTest extends TestCaseBase
{
    use WithClient;

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
            $output->data()
        );
    }
}
