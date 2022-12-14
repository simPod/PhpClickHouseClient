<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final class CurrentDatabase
{
    public static function run(ClickHouseClient $clickHouseClient): string
    {
        /** @var JsonEachRow<array{database: string}> $format */
        $format = new JsonEachRow();

        $currentDatabase = $clickHouseClient->select(
            <<<'CLICKHOUSE'
SELECT currentDatabase() AS database
CLICKHOUSE,
            $format,
        );

        return $currentDatabase->data[0]['database'];
    }
}
