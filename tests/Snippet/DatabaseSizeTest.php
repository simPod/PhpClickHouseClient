<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\DatabaseSize;
use SimPod\ClickHouseClient\Tests\ClickHouseVersion;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(DatabaseSize::class)]
final class DatabaseSizeTest extends TestCaseBase
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
        self::assertSame(0, DatabaseSize::run(self::$client));

        self::$client->insert('test', [[new DateTimeImmutable('2020-08-01 00:11:22'), 1]]);

        if (ClickHouseVersion::get() >= 2307) {
            $expectedSize = 316;
        } elseif (ClickHouseVersion::get() >= 2305) {
            $expectedSize = 162;
        } else {
            $expectedSize = 150;
        }

        self::assertSame($expectedSize, DatabaseSize::run(self::$client));
    }

    public function tearDown(): void
    {
        self::$client->executeQuery('DROP TABLE test');
    }
}
