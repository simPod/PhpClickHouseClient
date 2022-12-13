<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function sprintf;

final class Parts
{
    /** @return array<array<string, mixed>> */
    public static function run(ClickHouseClient $clickHouseClient, string $table, bool|null $active = null): array
    {
        $whereActiveClause = $active === null ? '' : sprintf(' AND active = %d', $active);

        /** @var JsonEachRow<array<string, mixed>> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->selectWithParams(
            <<<CLICKHOUSE
SELECT *
FROM system.parts
WHERE table=:table $whereActiveClause
ORDER BY max_date
CLICKHOUSE,
            ['table' => $table],
            $format,
        );

        return $output->data;
    }
}
