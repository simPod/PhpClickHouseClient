<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimPod\ClickHouseClient\Logger\PsrLogger;

/** @covers \SimPod\ClickHouseClient\Logger\PsrLogger */
final class PsrLoggerTest extends TestCase
{
    public function testStartQuery() : void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->withConsecutive(['SELECT 1', []]);

        $psrLogger = new PsrLogger($logger);
        $psrLogger->startQuery('oioi', 'SELECT 1');
        $psrLogger->stopQuery('oioi');
    }
}
