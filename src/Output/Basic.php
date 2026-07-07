<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Psr\Http\Message\StreamInterface;

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final readonly class Basic implements Output
{
    public string $contents;

    public function __construct(string|StreamInterface $contents)
    {
        $this->contents = $contents instanceof StreamInterface ? $contents->__toString() : $contents;
    }
}
