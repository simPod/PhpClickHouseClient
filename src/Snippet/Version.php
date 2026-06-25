<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Snippet;

use Psr\Http\Client\ClientExceptionInterface;
use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Format\JsonEachRow;

use function iterator_to_array;

final readonly class Version
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
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

        return iterator_to_array($output->data, preserve_keys: false)[0]['version'];
    }
}
