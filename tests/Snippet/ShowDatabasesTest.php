<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\ShowDatabases;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;
use function array_filter;
use function array_shift;
use function array_values;
use function count;
use function strpos;

/** @covers \SimPod\ClickHouseClient\Snippet\ShowDatabases */
final class ShowDatabasesTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        $databases = ShowDatabases::run($this->client);
        self::assertGreaterThan(2, count($databases)); // Default, system, at least one test database

        $databases = array_filter(
            $databases,
            function (string $database) : bool {
                // Filter out zombie test databases
                return strpos($database, 'clickhouse_client_test__') !== 0 || $database === $this->currentDbName;
            }
        );

        $databases = array_values($databases);

        // BC
        if ($databases[0] === '_temporary_and_external_tables') {
            array_shift($databases);
        }

        self::assertSame(
            [
                $this->currentDbName,
                'default',
                'system',
            ],
            $databases
        );
    }
}
