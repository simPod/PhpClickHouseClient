<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient;

use RuntimeException;

/**
 * @internal
 *
 * @phpstan-return never
 */
function absurd(): never
{
    throw new RuntimeException('Called `absurd` function which should never be called');
}
