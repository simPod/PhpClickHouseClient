<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\Parts;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function assert;
use function is_string;

#[CoversClass(Parts::class)]
final class PartsTest extends TestCaseBase
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
        self::$client->insert('test', [[new DateTimeImmutable('2020-08-01 00:11:22'), 1]]);
    }

    public function testRun(): void
    {
        $currentDbName = self::$currentDbName;
        assert(is_string($currentDbName));

        self::assertCount(1, Parts::run(self::$client, $currentDbName, 'test'));
        self::assertCount(0, Parts::run(self::$client, $currentDbName, 'test', false));
    }
}
