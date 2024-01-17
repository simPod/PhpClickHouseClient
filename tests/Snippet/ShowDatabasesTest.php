<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\ShowDatabases;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function array_filter;
use function array_shift;
use function array_values;
use function count;
use function str_starts_with;

#[CoversClass(ShowDatabases::class)]
final class ShowDatabasesTest extends TestCaseBase
{
    use WithClient;

    public function testRun(): void
    {
        $databases = ShowDatabases::run(self::$client);
        self::assertGreaterThan(2, count($databases)); // Default, system, at least one test database

        $databases = array_filter(
            $databases,
            static fn (string $database): bool => ! str_starts_with($database, 'clickhouse_client_test__')
                || $database === self::$currentDbName
        );

        $databases = array_values($databases);

        // BC
        if ($databases[0] === '_temporary_and_external_tables') {
            array_shift($databases);
        }

        $expected = [
            'INFORMATION_SCHEMA',
            self::$currentDbName,
            'default',
            'information_schema',
            'system',
        ];

        self::assertSame($expected, $databases);
    }
}
