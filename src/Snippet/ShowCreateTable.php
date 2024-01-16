<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final class ShowCreateTable
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public static function run(ClickHouseClient $clickHouseClient, string $tableName): string
    {
        /** @var JsonEachRow<array{statement: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<CLICKHOUSE
SHOW CREATE TABLE $tableName
CLICKHOUSE,
            $format,
        );

        return $output->data[0]['statement'];
    }
}
