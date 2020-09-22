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

    /** @inheritdoc */
    public function startQuery(string $sql, array $params = []) : void
    {
        $this->logger->debug($sql, $params);
    }

    public function stopQuery() : void
    {
    }
}
