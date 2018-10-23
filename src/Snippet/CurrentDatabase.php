<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final class CurrentDatabase
{
    public static function run(ClickHouseClient $clickHouseClient) : string
    {
        $currentDatabase = $clickHouseClient->select(
            <<<CLICKHOUSE
SELECT currentDatabase() AS database
CLICKHOUSE,
            new JsonEachRow()
        );

        return $currentDatabase->data()[0]['database'];
    }
}
