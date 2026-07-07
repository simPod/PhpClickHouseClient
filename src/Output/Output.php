<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Psr\Http\Message\StreamInterface;

/** @template T */
interface Output
{
    public function __construct(string|StreamInterface $contents);
}
