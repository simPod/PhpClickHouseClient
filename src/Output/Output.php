<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

interface Output
{
    public function __construct(string $contents);
}
