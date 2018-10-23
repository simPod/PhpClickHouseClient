<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

/** @psalm-immutable */
final class TabSeparated implements Output
{
    /** @var string */
    public $contents;

    public function __construct(string $contents)
    {
        $this->contents = $contents;
    }
}
