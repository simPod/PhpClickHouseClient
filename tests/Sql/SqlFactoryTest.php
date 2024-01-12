<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Sql\Expression;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(SqlFactory::class)]
final class SqlFactoryTest extends TestCaseBase
{
    /** @param array<string, mixed> $parameters */
    #[DataProvider('providerCreateWithParameters')]
    public function testCreateWithParameters(string $expectedSql, string $sqlWithPlaceholders, array $parameters): void
    {
        $sql = (new SqlFactory(new ValueFormatter()))->createWithParameters($sqlWithPlaceholders, $parameters);

        self::assertSame($expectedSql, $sql);
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function providerCreateWithParameters(): iterable
    {
        yield 'empty parameters' => [
            <<<'CLICKHOUSE'
SELECT 1
CLICKHOUSE,
            <<<'CLICKHOUSE'
SELECT 1
CLICKHOUSE,
            [],
        ];

        yield 'string parameter' => [
            <<<'CLICKHOUSE'
SELECT 'ping'
CLICKHOUSE,
            <<<'CLICKHOUSE'
SELECT :ping
CLICKHOUSE,
            ['ping' => 'ping'],
        ];

        yield 'two parameters, 1. name substring of 2.' => [
            <<<'CLICKHOUSE'
SELECT 1, 2
CLICKHOUSE,
            <<<'CLICKHOUSE'
SELECT :ping, :pingpong
CLICKHOUSE,
            [
                'ping'     => 1,
                'pingpong' => 2,
            ],
        ];

        yield 'null filter' => [
            <<<'CLICKHOUSE'
SELECT 1 FROM system.one WHERE dummy IS NULL
CLICKHOUSE,
            <<<'CLICKHOUSE'
SELECT 1 FROM system.one WHERE dummy = :null
CLICKHOUSE,
            ['null' => null],
        ];

        yield 'escape backslash' => [
            <<<'CLICKHOUSE'
SELECT toIPv6('x\\')
CLICKHOUSE,
            <<<'CLICKHOUSE'
SELECT :ping
CLICKHOUSE,
            [
                'ping' => Expression::new("toIPv6('x\\\\')"),
            ],
        ];
    }
}
