<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Logger;

interface SqlLogger
{
    public function startQuery(string $id, string $sql): void;

    public function stopQuery(string $id): void;
}
