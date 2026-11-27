<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use RuntimeException;
use SensitiveParameter;

use function is_finite;
use function strpbrk;

use const PHP_INT_SIZE;

final readonly class ConnectionConfig
{
    public int $port;

    public function __construct(
        public string $host,
        int|null $port = null,
        public string $database = 'default',
        public string $user = 'default',
        #[SensitiveParameter]
        public string $password = '',
        public bool $tls = false,
        public float $connectTimeout = 5.0,
        public float $readTimeout = 30.0,
        public string|null $caFile = null,
        public string|null $serverName = null,
    ) {
        $this->port = $port ?? ($tls ? 9440 : 9000);
        if (PHP_INT_SIZE !== 8) {
            throw new RuntimeException('Native TCP requires 64-bit PHP.');
        }

        if ($host === '' || strpbrk($host, "/@?#\0\r\n\t ") !== false) {
            throw new RuntimeException('Native TCP host must be a hostname or IP address, not a URL.');
        }

        if ($this->port < 1 || $this->port > 65535 || $user === '') {
            throw new RuntimeException('Native TCP requires a valid port and a non-empty user.');
        }

        if (! is_finite($connectTimeout) || ! is_finite($readTimeout) || $connectTimeout <= 0 || $readTimeout <= 0) {
            throw new RuntimeException('Native TCP timeouts must be finite positive seconds.');
        }

        if (! $tls && ($caFile !== null || $serverName !== null)) {
            throw new RuntimeException('TLS options require TLS to be enabled.');
        }
    }
}
