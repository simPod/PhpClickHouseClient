<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\ByteStream\Payload;
use Amp\Future;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;

interface ClickHouseAsyncClient
{
    /**
     * @param Format<O> $outputFormat
     *
     * @return Future<O>
     *
     * @template O of Output
     */
    public function select(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;

    /**
     * @param array<string, mixed>            $params
     * @param Format<O>                       $outputFormat
     *
     * @return Future<O>
     *
     * @template O of Output
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;

    /**
     * @param Format<O> $outputFormat
     *
     * @return Future<Payload>
     *
     * @template O of Output
     */
    public function selectStream(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;

    /**
     * @param array<string, mixed>            $params
     * @param Format<O>                       $outputFormat
     *
     * @return Future<Payload>
     *
     * @template O of Output
     */
    public function selectStreamWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Future;
}
