<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\Json;

final readonly class CurrentDatabase
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public static function run(ClickHouseClient $clickHouseClient): string
    {
        /** @var Json<array{database: string}> $format */
        $format = new Json();

        $currentDatabase = $clickHouseClient->select(
            <<<'CLICKHOUSE'
            SELECT currentDatabase() AS database
            CLICKHOUSE,
            $format,
        );

        return $currentDatabase->data[0]['database'];
    }
}
