<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function trim;

final class ShowCreateTable
{
    public static function run(ClickHouseClient $clickHouseClient, string $tableName) : string
    {
        /** @var JsonEachRow<array{statement: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW CREATE TABLE $tableName
CLICKHOUSE,
            $format
        );

        return trim($output->data[0]['statement']);
    }
}
