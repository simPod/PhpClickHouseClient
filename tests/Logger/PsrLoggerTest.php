<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Logger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimPod\ClickHouseClient\Logger\PsrLogger;

#[CoversClass(PsrLogger::class)]
final class PsrLoggerTest extends TestCase
{
    public function testStartQuery(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with('SELECT 1', []);

        $psrLogger = new PsrLogger($logger);
        $psrLogger->startQuery('oioi', 'SELECT 1');
        $psrLogger->stopQuery('oioi');
    }
}
