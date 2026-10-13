<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

final readonly class Result
{
    /**
     * @param list<Column> $columns
     * @param list<list<mixed>> $rows
     * @param list<list<mixed>> $totals
     * @param list<list<mixed>> $extremes
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public array $totals = [],
        public array $extremes = [],
    ) {
    }
}
