<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final readonly class Null_ implements Output
{
    public function __construct(string $_)
    {
    }
}
