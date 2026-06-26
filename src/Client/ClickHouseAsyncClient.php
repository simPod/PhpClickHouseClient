<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\Future;
use GuzzleHttp\Promise\PromiseInterface;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;

interface ClickHouseAsyncClient
{
    /**
     * @param Format<O> $outputFormat
     *
     * @template O of Output
     */
    public function select(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): PromiseInterface;

    /**
     * @param Format<O> $outputFormat
     *
     * @return Future<O>
     *
     * @template O of Output
     */
    public function selectFuture(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;

    /**
     * @param array<string, mixed>            $params
     * @param Format<O>                       $outputFormat
     *
     * @template O of Output
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): PromiseInterface;

    /**
     * @param array<string, mixed>            $params
     * @param Format<O>                       $outputFormat
     *
     * @return Future<O>
     *
     * @template O of Output
     */
    public function selectWithParamsFuture(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;
}
