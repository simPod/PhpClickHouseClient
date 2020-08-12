<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function assert;
use function is_string;

final class Version
{
    public static function run(ClickHouseClient $clickHouseClient) : string
    {
        $version = $clickHouseClient->select(
            <<<CLICKHOUSE
SELECT version() AS version
CLICKHOUSE,
            new JsonEachRow()
        );

        /** @psalm-suppress MixedAssignment */
        $version = $version->data[0]['version'];
        assert(is_string($version));

        return $version;
    }
}
