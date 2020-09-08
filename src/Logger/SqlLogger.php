<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

interface SqlLogger
{
    /** @param array<string, mixed> $params */
    public function startQuery(string $sql, array $params = []) : void;

    public function stopQuery() : void;
}
