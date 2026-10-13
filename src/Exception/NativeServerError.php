<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use RuntimeException;

final class NativeServerError extends RuntimeException implements ClickHouseClientException
{
    public function __construct(
        int $code,
        public readonly string $name,
        string $message,
        public readonly string $serverStackTrace,
        NativeServerError|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
