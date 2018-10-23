<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

interface ClickHouseClient
{
    public function executeQuery(string $query) : void;

    /** @param array<string, mixed> $queryParameters */
    public function executeQueryWithParameters(string $query, array $queryParameters) : void;

    /**
     * @param array<string, float|int|string> $requestParameters
     *
     * @psalm-template       O of Output
     * @psalm-param          Format<O> $outputFormat
     * @psalm-return         O
     */
    public function select(string $query, Format $outputFormat, array $requestParameters = []) : Output;

    /**
     * @param array<string, float|int|string> $requestParameters
     * @param array<string, mixed>            $queryParameters
     *
     * @psalm-template       O of Output
     * @psalm-param          Format<O> $outputFormat
     * @psalm-return         O
     */
    public function selectWithParameters(
        string $query,
        array $queryParameters,
        Format $outputFormat,
        array $requestParameters = []
    ) : Output;

    /**
     * @param array<array<mixed>> $values
     * @param array<string>|null  $columns
     */
    public function insert(string $table, array $values, ?array $columns = null) : void;

    /**
     * @psalm-template       O of Output
     * @psalm-param          Format<O> $inputFormat
     */
    public function insertWithFormat(string $table, Format $inputFormat, string $data) : void;
}
