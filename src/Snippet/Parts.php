<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use function Safe\sprintf;

final class Parts
{
    /** @return array<array<string, mixed>> */
    public static function run(ClickHouseClient $clickHouseClient, string $table, ?bool $active = null) : array
    {
        $whereActiveClause = $active === null ? '' : sprintf(' AND active = %s', (int) $active);

        $currentDatabase = $clickHouseClient->selectWithParameters(
            <<<CLICKHOUSE
SELECT *
FROM system.parts
WHERE table=:table $whereActiveClause
ORDER BY max_date
CLICKHOUSE,
            ['table' => $table],
            new JsonEachRow()
        );

        return $currentDatabase->data;
    }
}
