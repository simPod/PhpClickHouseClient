<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final readonly class ShowDatabases
{
    /**
     * @return list<string>
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public static function run(ClickHouseClient $clickHouseClient): array
    {
        /** @var JsonEachRow<array{name: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<'CLICKHOUSE'
            SHOW DATABASES
            CLICKHOUSE,
            $format,
        );

        $databases = [];

        foreach ($output->data as $database) {
            $databases[] = $database['name'];
        }

        return $databases;
    }
}
