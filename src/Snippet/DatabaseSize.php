<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Sql\Expression;

final class DatabaseSize
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public static function run(ClickHouseClient $clickHouseClient, string|null $databaseName = null): int
    {
        /** @var JsonEachRow<array{size: string|null}> $format */
        $format = new JsonEachRow();

        $currentDatabase = $clickHouseClient->selectWithParams(
            <<<'CLICKHOUSE'
SELECT sum(bytes) AS size
FROM system.parts
WHERE active AND database=:database
CLICKHOUSE,
            ['database' => $databaseName ?? Expression::new('currentDatabase()')],
            $format,
        );

        return (int) $currentDatabase->data[0]['size'];
    }
}
