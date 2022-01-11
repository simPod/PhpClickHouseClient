<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

final class RequestOptions
{
    public string $sql;

    /** @var array<string, string|array<string>> */
    public array $headers;

    /** @var array<string, float|int|string> */
    public array $queryParams;

    /**
     * @param array<string, string|array<string>> $defaultHeaders
     * @param array<string, string|array<string>> $requestHeaders
     * @param array<string, float|int|string> $defaultQueryParams
     * @param array<string, float|int|string> $requestParams
     */
    public function __construct(
        string $sql,
        array $defaultHeaders,
        array $requestHeaders,
        array $defaultQueryParams,
        array $requestParams
    ) {
        $this->sql         = $sql;
        $this->headers     = $defaultHeaders + $requestHeaders;
        $this->queryParams = $defaultQueryParams + $requestParams;
    }
}
