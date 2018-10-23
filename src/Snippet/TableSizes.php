<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Sql\Expression;

final class TableSizes
{
    /** @return array<array<string, mixed>> */
    public static function run(ClickHouseClient $clickHouseClient, ?string $databaseName = null) : array
    {
        $currentDatabase = $clickHouseClient->selectWithParameters(
            <<<CLICKHOUSE
SELECT 
    name AS table,
    database,
    max(size) AS size,
    min(min_date) AS min_date,
    max(max_date) AS max_date
FROM system.tables
ANY LEFT JOIN (
    SELECT 
        table,
        database,
        sum(bytes) AS size,
        min(min_date) AS min_date,
        max(max_date) AS max_date
    FROM system.parts 
    WHERE active AND database = :database
    GROUP BY table,database
) USING ( table, database )
WHERE database = :database AND storage_policy <> ''
GROUP BY table, database
CLICKHOUSE,
            ['database' => $databaseName ?? Expression::new('currentDatabase()')],
            new JsonEachRow()
        );

        return $currentDatabase->data;
    }
}
