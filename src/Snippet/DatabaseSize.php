<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\Json;
use SimPod\ClickHouseClient\Sql\Expression;

final readonly class DatabaseSize
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public static function run(ClickHouseClient $clickHouseClient, string|null $databaseName = null): int
    {
        /** @var Json<array{size: string|null}> $format */
        $format = new Json();

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
