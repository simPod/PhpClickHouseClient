<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final class Version
{
    public static function run(ClickHouseClient $clickHouseClient): string
    {
        /** @var JsonEachRow<array{version: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<'CLICKHOUSE'
            SELECT version() AS version
            CLICKHOUSE,
            $format,
        );

        return $output->data[0]['version'];
    }
}
