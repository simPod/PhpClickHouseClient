<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final readonly class Basic implements Output
{
    public function __construct(public string $contents)
    {
    }
}
