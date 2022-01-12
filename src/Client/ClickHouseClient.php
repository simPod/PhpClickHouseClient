<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use SimPod\ClickHouseClient\Format\Format;
use SimPod\ClickHouseClient\Output\Output;

interface ClickHouseClient
{
    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     */
    public function executeQuery(string $query, array $requestHeaders = [], array $requestQueryParams = []) : void;

    /**
     * @param array<string, mixed> $statementParams
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     */
    public function executeQueryWithParameters(
        string $query,
        array $statementParams,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : void;

    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     * @psalm-param  Format<O> $outputFormat
     *
     * @psalm-return O
     *
     * @template     O of Output
     */
    public function select(
        string $query,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : Output;

    /**
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $requestQueryParams
     * @param array<string, mixed> $statementParams
     * @psalm-param  Format<O> $outputFormat
     *
     * @psalm-return O
     *
     * @template     O of Output
     */
    public function selectWithParams(
        string $query,
        array $statementParams,
        Format $outputFormat,
        array $requestHeaders = [],
        array $requestQueryParams = []
    ) : Output;

    /**
     * @param array<array<mixed>> $values
     * @param array<string>|null $columns
     */
    public function insert(string $table, array $values, ?array $columns = null) : void;

    /**
     * @psalm-param Format<O> $inputFormat
     *
     * @template    O of Output
     */
    public function insertWithFormat(string $table, Format $inputFormat, string $data) : void;
}
