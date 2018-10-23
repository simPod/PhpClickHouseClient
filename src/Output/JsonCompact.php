<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use function Safe\json_decode;

final class JsonCompact implements Output
{
    /** @var array<array<mixed>> */
    private $data;

    /** @var array<mixed> */
    private $meta;

    /** @var int */
    private $rows;

    /** @var int|null */
    private $rowsBeforeLimitAtLeast;

    /** @var array{elapsed: float, rows_read: int, bytes_read: int} */
    private $statistics;

    public function __construct(string $contentsJson)
    {
        /** @var array{data: array<array<mixed>>, meta: array<mixed>, rows: int, statistics: array{elapsed: float, rows_read: int, bytes_read: int}} $contents */
        $contents                     = json_decode($contentsJson, true);
        $this->data                   = $contents['data'];
        $this->meta                   = $contents['meta'];
        $this->rows                   = $contents['rows'];
        $this->rowsBeforeLimitAtLeast = $contents['rows_before_limit_at_least'] ?? null;
        $this->statistics             = $contents['statistics'];
    }

    /**
     * @return array<mixed>
     */
    public function meta() : array
    {
        return $this->meta;
    }

    /**
     * @return array<array<mixed>>
     */
    public function data() : array
    {
        return $this->data;
    }

    public function rows() : int
    {
        return $this->rows;
    }

    public function rowsBeforeLimitAtLeast() : ?int
    {
        return $this->rowsBeforeLimitAtLeast;
    }

    /**
     * @return array{elapsed: float, rows_read: int, bytes_read: int}
     */
    public function statistics() : array
    {
        return $this->statistics;
    }
}
