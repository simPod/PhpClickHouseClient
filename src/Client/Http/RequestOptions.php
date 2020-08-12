<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

final class RequestOptions
{
    public string $sql;

    /** @var array<string, float|int|string> */
    public array $parameters;

    /**
     * @param array<string, float|int|string> $defaultParameters
     * @param array<string, float|int|string> $requestParameters
     */
    public function __construct(string $sql, array $defaultParameters, array $requestParameters)
    {
        $this->sql        = $sql;
        $this->parameters = $defaultParameters + $requestParameters;
    }
}
