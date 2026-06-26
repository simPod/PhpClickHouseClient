<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Generator;
use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;

final readonly class ShowDatabases
{
    /**
     * @return Generator<int, string>
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public static function run(ClickHouseClient $clickHouseClient): Generator
    {
        /** @var JsonEachRow<array{name: string}> $format */
        $format = new JsonEachRow();

        $output = $clickHouseClient->select(
            <<<'CLICKHOUSE'
            SHOW DATABASES
            CLICKHOUSE,
            $format,
        );

        foreach ($output->data as $database) {
            yield $database['name'];
        }
    }
}
