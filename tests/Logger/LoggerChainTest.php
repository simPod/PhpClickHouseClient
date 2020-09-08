<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Logger;

use PHPUnit\Framework\TestCase;
use SimPod\ClickHouseClient\Logger\LoggerChain;
use SimPod\ClickHouseClient\Logger\SqlLogger;

final class LoggerChainTest extends TestCase
{
    public function testLog() : void
    {
        $logger = new class implements SqlLogger {
            public ?string $sql = null;

            /** @var array<string, mixed>|null $params */
            public ?array $params = null;

            public bool $started = false;

            public bool $stopped = false;

            /** @inheritDoc */
            public function startQuery(string $sql, array $params = []) : void
            {
                $this->sql     = $sql;
                $this->params  = $params;
                $this->started = true;
            }

            public function stopQuery() : void
            {
                $this->stopped = true;
            }
        };

        $chain = new LoggerChain([$logger]);

        $chain->startQuery('sql', []);
        $chain->stopQuery();

        self::assertSame('sql', $logger->sql);
        self::assertSame([], $logger->params);
        self::assertTrue($logger->started);
        self::assertTrue($logger->stopped);
    }
}
