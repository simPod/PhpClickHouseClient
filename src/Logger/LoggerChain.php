<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

final class LoggerChain implements SqlLogger
{
    /** @param SqlLogger[] $loggers */
    public function __construct(private array $loggers = [])
    {
    }

    public function startQuery(string $id, string $sql): void
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($id, $sql);
        }
    }

    public function stopQuery(string $id): void
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery($id);
        }
    }
}
