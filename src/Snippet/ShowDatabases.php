<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function array_map;

final class ShowDatabases
{
    /** @return array<string> */
    public static function run(ClickHouseClient $clickHouseClient) : array
    {
        /** @var JsonEachRow<array{name: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW DATABASES
CLICKHOUSE,
            $format
        );

        return array_map(
            static function (array $database) : string {
                return $database['name'];
            },
            $output->data
        );
    }
}
