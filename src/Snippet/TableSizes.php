<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Sql\Expression;

/** @phpstan-type Entry array{table: string, database: string, size: string, min_date: string, max_date: string} */
final class TableSizes
{
    /**
     * @return array<Entry>
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public static function run(ClickHouseClient $clickHouseClient, string|null $databaseName = null): array
    {
        /** @var JsonEachRow<Entry> $format */
        $format = new JsonEachRow();

        return $clickHouseClient->selectWithParams(
            <<<'CLICKHOUSE'
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
) parts USING ( table, database )
WHERE database = :database AND storage_policy <> ''
GROUP BY table, database
CLICKHOUSE,
            ['database' => $databaseName ?? Expression::new('currentDatabase()')],
            $format,
        )->data;
    }
}
