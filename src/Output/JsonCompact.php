<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use function Safe\json_decode;

/** @psalm-immutable */
final class JsonCompact implements Output
{
    /** @var array<array<mixed>> */
    public $data;

    /** @var array<mixed> */
    public $meta;

    /** @var int */
    public $rows;

    /** @var int|null */
    public $rowsBeforeLimitAtLeast;

    /** @var array{elapsed: float, rows_read: int, bytes_read: int} */
    public $statistics;

    public function __construct(string $contentsJson)
    {
        // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
        /**
         * @var array{data: array<array<mixed>>, meta: array<mixed>, rows: int, rows_before_limit_at_least: int, statistics: array{elapsed: float, rows_read: int, bytes_read: int}} $contents
         * @psalm-suppress ImpureFunctionCall
         */
        $contents                     = json_decode($contentsJson, true);
        $this->data                   = $contents['data'];
        $this->meta                   = $contents['meta'];
        $this->rows                   = $contents['rows'];
        $this->rowsBeforeLimitAtLeast = $contents['rows_before_limit_at_least'] ?? null;
        $this->statistics             = $contents['statistics'];
    }
}
