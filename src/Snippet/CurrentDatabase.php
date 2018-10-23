<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use function assert;
use function is_string;

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

        $databaseName = $currentDatabase->data[0]['database'];
        assert(is_string($databaseName));

        return $databaseName;
    }
}
