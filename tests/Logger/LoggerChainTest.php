<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Logger;

use PHPUnit\Framework\TestCase;
use SimPod\ClickHouseClient\Logger\LoggerChain;
use SimPod\ClickHouseClient\Logger\SqlLogger;

final class LoggerChainTest extends TestCase
{
    public function testLog(): void
    {
        $logger = new class implements SqlLogger {
            public string $id;

            public string|null $sql = null;

            public bool $started = false;

            public bool $stopped = false;

            public function startQuery(string $id, string $sql): void
            {
                $this->id      = $id;
                $this->sql     = $sql;
                $this->started = true;
            }

            public function stopQuery(string $id): void
            {
                $this->stopped = true;
            }
        };

        $chain = new LoggerChain([$logger]);

        $chain->startQuery('a', 'sql');
        $chain->stopQuery('a');

        self::assertSame('sql', $logger->sql);
        self::assertTrue($logger->started);
        self::assertTrue($logger->stopped);
    }
}
