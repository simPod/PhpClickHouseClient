<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Psr\Http\Message\StreamInterface;

// phpcs:disable Squiz.Classes.ValidClassName.NotPascalCase

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final readonly class Null_ implements Output
{
    public function __construct(string|StreamInterface $_)
    {
    }
}
