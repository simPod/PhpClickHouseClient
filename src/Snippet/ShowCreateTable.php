<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use Webmozart\Assert\Assert;
use function trim;

final class ShowCreateTable
{
    public static function run(ClickHouseClient $clickHouseClient, string $tableName) : string
    {
        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW CREATE TABLE $tableName
CLICKHOUSE,
            new JsonEachRow()
        );

        $statement = $output->data[0]['statement'];
        Assert::string($statement);

        return trim($statement);
    }
}
