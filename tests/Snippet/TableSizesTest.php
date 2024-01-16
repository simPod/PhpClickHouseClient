<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\TableSizes;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(TableSizes::class)]
final class TableSizesTest extends TestCaseBase
{
    use WithClient;

    public function setUp(): void
    {
        self::$client->executeQuery(
            <<<'CLICKHOUSE'
CREATE TABLE test (
    a_date  DateTime,
    value   Int8
)
ENGINE = MergeTree
    PARTITION BY toDate(a_date)
    ORDER BY (value)
CLICKHOUSE,
        );
    }

    public function testRun(): void
    {
        self::$client->insert('test', [[new DateTimeImmutable(), 1]]);

        self::assertCount(1, TableSizes::run(self::$client));
    }

    public function testRunOnNonexistentDatabase(): void
    {
        self::assertSame([], TableSizes::run(self::$client, 'does not exist'));
    }

    public function tearDown(): void
    {
        self::$client->executeQuery('DROP TABLE test');
    }
}
