<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\TabSeparated;
use function trim;

final class ShowCreateTable
{
    public static function run(ClickHouseClient $clickHouseClient, string $tableName) : string
    {
        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW CREATE TABLE $tableName
CLICKHOUSE,
            new TabSeparated()
        );

        return trim($output->contents);
    }
}
