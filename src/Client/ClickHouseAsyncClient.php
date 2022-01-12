<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use GuzzleHttp\Promise\PromiseInterface;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

/** @see Output hack for IDE to preserve `use` */
interface ClickHouseAsyncClient
{
    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     * @psalm-param    Format<O> $outputFormat
     *
     * @template O of Output
     */
    public function select(
        string $sql,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : PromiseInterface;

    /**
     * @param array<string, mixed>            $statementParams
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     * @psalm-param    Format<O> $outputFormat
     *
     * @template O of Output
     */
    public function selectWithParams(
        string $sql,
        array $statementParams,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : PromiseInterface;
}
