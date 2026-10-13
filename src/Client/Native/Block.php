<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

/** @internal */
final readonly class Block
{
    /**
     * @param list<Column> $columns
     * @param list<list<mixed>> $rows
     */
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public int $estimatedBytes = 0,
    ) {
    }
}
