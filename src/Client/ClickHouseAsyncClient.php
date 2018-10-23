<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use GuzzleHttp\Promise\PromiseInterface;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

interface ClickHouseAsyncClient
{
    /**
     * @see Output hack for IDE to preserve `use`
     *
     * @param array<string, float|int|string> $requestParameters
     *
     * @psalm-template O of Output
     * @psalm-param    Format<O> $outputFormat
     */
    public function select(string $sql, Format $outputFormat, array $requestParameters = []) : PromiseInterface;

    /**
     * @param array<string, float|int|string> $requestParameters
     * @param array<string, mixed>            $queryParameters
     *
     * @psalm-template O of Output
     * @psalm-param    Format<O> $outputFormat
     */
    public function selectWithParameters(
        string $query,
        array $queryParameters,
        Format $outputFormat,
        array $requestParameters = []
    ) : PromiseInterface;
}
