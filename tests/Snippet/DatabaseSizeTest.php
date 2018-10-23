<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use DateTimeImmutable;
use SimPod\ClickHouseClient\Snippet\DatabaseSize;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\DatabaseSize */
final class DatabaseSizeTest extends TestCaseBase
{
    use WithClient;

    public function setUp() : void
    {
        $this->client->executeQuery(
            <<<CLICKHOUSE
CREATE TABLE test (
    a_date  DateTime,
    value   Int8
)
ENGINE = MergeTree
    PARTITION BY toDate(a_date)
    ORDER BY (value)
CLICKHOUSE
        );
    }

    public function testRun() : void
    {
        self::assertSame(0, DatabaseSize::run($this->client));

        $this->client->insert('test', [[new DateTimeImmutable(), 1]]);

        self::assertSame(166, DatabaseSize::run($this->client));
    }

    public function tearDown() : void
    {
        $this->client->executeQuery('DROP TABLE test');
    }
}
