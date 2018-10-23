<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Sql\Expression;
use function assert;

final class DatabaseSize
{
    public static function run(ClickHouseClient $clickHouseClient, ?string $databaseName = null) : int
    {
        $currentDatabase = $clickHouseClient->selectWithParameters(
            <<<CLICKHOUSE
SELECT sum(bytes) AS size
FROM system.parts
WHERE active AND database=:database
CLICKHOUSE,
            ['database' => $databaseName ?? Expression::new('currentDatabase()')],
            new JsonEachRow()
        );

        /** @psalm-suppress MixedAssignment */
        $size = $currentDatabase->data[0]['size'];

        assert($size !== null);

        return (int) $size;
    }
}
