<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Amp\ByteStream\Payload;
use Amp\Cancellation;
use Amp\Future;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;

/**
 * Cancellation applies to the HTTP request and response consumption, including later reads of a returned payload.
 * Cancelled operations fail with Amp\CancelledException through their future or returned payload.
 */
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
        Cancellation|null $cancellation = null,
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
        Cancellation|null $cancellation = null,
    ): Future;

    /**
     * @param Format<Output<mixed>> $outputFormat
     *
     * @return Future<Payload>
     */
    public function selectStream(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
    ): Future;

    /**
     * @param array<string, mixed>  $params
     * @param Format<Output<mixed>> $outputFormat
     *
     * @return Future<Payload>
     */
    public function selectStreamWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
        Cancellation|null $cancellation = null,
    ): Future;
}
