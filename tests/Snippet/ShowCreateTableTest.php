<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\ShowCreateTable;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

use function Safe\preg_replace;
use function str_replace;

#[CoversClass(ShowCreateTable::class)]
final class ShowCreateTableTest extends TestCaseBase
{
    use WithClient;

    public function testRun(): void
    {
        $dbName = $this->currentDbName;
        $sql    = <<<CLICKHOUSE
CREATE TABLE $dbName.test (`date` Date) ENGINE = Memory
CLICKHOUSE;

        $this->client->executeQuery($sql);

        $createTableSql = ShowCreateTable::run($this->client, 'test');

        // BC
        $createTableSql = str_replace(
            ['( ', ' )'],
            ['(', ')'],
            preg_replace(
                '!\s+!',
                ' ',
                str_replace('\n', ' ', $createTableSql),
            ),
        );

        self::assertSame($sql, $createTableSql);
    }
}
