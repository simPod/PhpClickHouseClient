<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

use Psr\Log\LoggerInterface;

final class PsrLogger implements SqlLogger
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function startQuery(string $id, string $sql) : void
    {
        $this->logger->debug($sql);
    }

    public function stopQuery(string $id) : void
    {
    }
}
