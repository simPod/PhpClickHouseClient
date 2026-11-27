<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use RuntimeException;

/** @internal */
final class DecodeBudget
{
    // Conservative accounting for PHP containers, cells, and raw byte payloads.
    public private(set) int $used = 0;

    public function consume(int $bytes): void
    {
        if ($bytes < 0 || $bytes > 67108864 - $this->used) {
            throw new RuntimeException('Native decoded data exceeds the 64 MiB allocation budget.');
        }

        $this->used += $bytes;
    }
}
