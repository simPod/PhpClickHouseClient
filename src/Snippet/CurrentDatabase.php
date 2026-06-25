<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function iterator_to_array;

final readonly class CurrentDatabase
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public static function run(ClickHouseClient $clickHouseClient): string
    {
        /** @var JsonEachRow<array{database: string}> $format */
        $format = new JsonEachRow();

        $currentDatabase = $clickHouseClient->select(
            <<<'CLICKHOUSE'
            SELECT currentDatabase() AS database
            CLICKHOUSE,
            $format,
        );

        return iterator_to_array($currentDatabase->data, preserve_keys: false)[0]['database'];
    }
}
