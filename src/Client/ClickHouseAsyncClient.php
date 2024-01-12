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
     * @param Format<O> $outputFormat
     * @param array<string, float|int|string> $settings
     *
     * @template O of Output
     */
    public function select(string $query, Format $outputFormat, array $settings = []): PromiseInterface;

    /**
     * @param array<string, mixed>            $params
     * @param Format<O>                       $outputFormat
     * @param array<string, float|int|string> $settings
     *
     * @template O of Output
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        array $settings = [],
    ): PromiseInterface;
}
