<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

/**
 * @psalm-immutable
 * @template T
 * @implements Output<T>
 */
final class TabSeparated implements Output
{
    public function __construct(public string $contents)
    {
    }
}
