<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

final class RequestOptions
{
    /** @var array<string, string|array<string>> */
    public array $headers;

    /** @var array<string, float|int|string> */
    public array $settings;

    /**
     * @param array<string, float|int|string> $defaultSettings
     * @param array<string, float|int|string> $querySettings
     */
    public function __construct(public string $sql, array $defaultSettings, array $querySettings)
    {
        $this->settings = $querySettings + $defaultSettings;
    }
}
