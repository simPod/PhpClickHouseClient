<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\ServerError;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;
use SimPod\ClickHouseClient\Schema\Table;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;

/** @phpstan-import-type Settings from SettingsProvider */
interface ClickHouseClient
{
    /**
     * @throws ClientExceptionInterface
     * @throws ServerError
     */
    public function executeQuery(string $query, SettingsProvider $settings = new EmptySettingsProvider()): void;

    /**
     * @param array<string, mixed> $params
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public function executeQueryWithParams(
        string $query,
        array $params,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void;

    /**
     * @param Format<O> $outputFormat
     *
     * @return O
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     *
     * @template O of Output
     */
    public function select(
        string $query,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Output;

    /**
     * @param array<string, mixed> $params
     * @param Format<O> $outputFormat
     *
     * @return O
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     *
     * @template O of Output
     */
    public function selectWithParams(
        string $query,
        array $params,
        Format $outputFormat,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Output;

    /**
     * @param array<array<mixed>> $values
     * @param list<string>|array<string, string>|null $columns
     *
     * @throws CannotInsert
     * @throws ClientExceptionInterface
     * @throws ServerError
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public function insert(
        Table|string $table,
        array $values,
        array|null $columns = null,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void;

    /**
     * @param Format<O> $inputFormat
     *
     * @throws ClientExceptionInterface
     * @throws ServerError
     *
     * @template O of Output
     */
    public function insertWithFormat(
        Table|string $table,
        Format $inputFormat,
        string $data,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void;

    /**
     * @param list<string> $columns
     * @param Format<Output<mixed>> $inputFormat
     *
     * @throws ClientExceptionInterface
     * @throws CannotInsert
     * @throws ServerError
     */
    public function insertPayload(
        Table|string $table,
        Format $inputFormat,
        StreamInterface $payload,
        array $columns = [],
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void;
}
