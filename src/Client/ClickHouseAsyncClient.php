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
     * @param Format<O>                       $outputFormat
     * @param array<string, float|int|string> $requestParameters
     *
     * @return    PromiseInterface<O>
     *
     * @template O of Output
     */
    public function select(string $sql, Format $outputFormat, array $requestParameters = []) : PromiseInterface;

    /**
     * @param array<string, mixed>            $queryParameters
     * @param Format<O>                       $outputFormat
     * @param array<string, float|int|string> $requestParameters
     *
     * @return    PromiseInterface<O>
     *
     * @template O of Output
     */
    public function selectWithParameters(
        string $query,
        array $queryParameters,
        Format $outputFormat,
        array $requestParameters = []
    ) : PromiseInterface;
}
