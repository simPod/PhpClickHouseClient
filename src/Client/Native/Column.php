<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

final readonly class Column
{
    public function __construct(public string $name, public string $type)
    {
    }
}
