<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

interface ClickHouseClient
{
    /** @param array<string, float|int|string> $settings */
    public function executeQuery(string $query, array $settings = []) : void;

    /**
     * @param array<string, mixed> $params
     * @param array<string, float|int|string> $settings
     */
    public function executeQueryWithParams(string $query, array $params, array $settings = []) : void;

    /**
     * @param array<string, float|int|string> $settings
     * @param Format<O> $outputFormat
     *
     * @return O
     *
     * @template O of Output
     */
    public function select(string $query, Format $outputFormat, array $settings = []) : Output;

    /**
     * @param array<string, float|int|string> $settings
     * @param array<string, mixed> $params
     * @param Format<O> $outputFormat
     *
     * @return O
     *
     * @template O of Output
     */
    public function selectWithParams(string $query, array $params, Format $outputFormat, array $settings = []) : Output;

    /**
     * @param array<array<mixed>> $values
     * @param array<string>|null $columns
     */
    public function insert(string $table, array $values, ?array $columns = null) : void;

    /**
     * @param Format<O> $inputFormat
     *
     * @template O of Output
     */
    public function insertWithFormat(string $table, Format $inputFormat, string $data) : void;
}
