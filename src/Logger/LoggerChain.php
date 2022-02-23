<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

use function array_filter;

final class LoggerChain implements SqlLogger
{
    /** @var SqlLogger[] */
    private array $loggers;

    /** @param SqlLogger[] $loggers */
    public function __construct(array $loggers = [])
    {
        $this->loggers = array_filter(
            $loggers,
            static fn (SqlLogger $logger): bool => ! $logger instanceof self
        );
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
