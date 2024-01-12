<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function sprintf;

final class Parts
{
    /**
     * @return array<array<string, mixed>>
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public static function run(
        ClickHouseClient $clickHouseClient,
        string $database,
        string $table,
        bool|null $active = null,
    ): array {
        $whereActiveClause = $active === null ? '' : sprintf(' AND active = %d', $active);

        /** @var JsonEachRow<array<string, mixed>> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->selectWithParams(
            <<<CLICKHOUSE
SELECT *
FROM system.parts
WHERE
    database = :database
    AND table = :table $whereActiveClause
ORDER BY max_date
CLICKHOUSE,
            [
                'database' => $database,
                'table' => $table,
            ],
            $format,
        );

        return $output->data;
    }
}
