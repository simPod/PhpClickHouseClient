<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

final class RequestOptions
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public array $params,
    ) {
    }
}
