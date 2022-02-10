<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use DateTimeImmutable;
use SimPod\ClickHouseClient\Snippet\TableSizes;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\TableSizes */
final class TableSizesTest extends TestCaseBase
{
    use WithClient;

    public function setUp(): void
    {
        $this->client->executeQuery(
            <<<'CLICKHOUSE'
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

    public function testRun(): void
    {
        $this->client->insert('test', [[new DateTimeImmutable(), 1]]);

        self::assertCount(1, TableSizes::run($this->client));
    }

    public function testRunOnNonexistentDatabase(): void
    {
        self::assertSame([], TableSizes::run($this->client, 'does not exist'));
    }

    public function tearDown(): void
    {
        $this->client->executeQuery('DROP TABLE test');
    }
}
