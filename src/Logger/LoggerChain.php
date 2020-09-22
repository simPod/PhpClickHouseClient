<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

final class LoggerChain implements SqlLogger
{
    /** @var SqlLogger[] */
    private array $loggers;

    /** @param SqlLogger[] $loggers */
    public function __construct(array $loggers = [])
    {
        $this->loggers = $loggers;
    }

    /** @inheritdoc */
    public function startQuery(string $sql, array $params = []) : void
    {
        foreach ($this->loggers as $logger) {
            $logger->startQuery($sql, $params);
        }
    }

    public function stopQuery() : void
    {
        foreach ($this->loggers as $logger) {
            $logger->stopQuery();
        }
    }
}
