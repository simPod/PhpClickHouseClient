<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use function array_map;
use function assert;
use function is_string;

final class ShowDatabases
{
    /** @return array<string> */
    public static function run(ClickHouseClient $clickHouseClient) : array
    {
        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW DATABASES
CLICKHOUSE,
            new JsonEachRow()
        );

        return array_map(
            static function (array $database) : string {
                $databaseName = $database['name'];
                assert(is_string($databaseName));

                return $databaseName;
            },
            $output->data
        );
    }
}
