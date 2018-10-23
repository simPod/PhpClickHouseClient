<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use GuzzleHttp\Promise\PromiseInterface;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

interface ClickHouseAsyncClient
{
    /**
     * @see Output hack for IDe to preserve `use`
     *
     * @phpstan-template O of Output
     * @phpstan-param    Format<O> $outputFormat
     */
    public function select(string $sql, Format $outputFormat) : PromiseInterface;
}
