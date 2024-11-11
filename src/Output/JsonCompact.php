<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use JsonException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final class JsonCompact implements Output
{
    /** @var list<T> */
    public array $data;

    /** @var array<mixed> */
    public array $meta;

    public int $rows;

    public int|null $rowsBeforeLimitAtLeast;

    /** @var array{elapsed: float, rows_read: int, bytes_read: int} */
    public array $statistics;

    /** @throws JsonException */
    public function __construct(string $contentsJson)
    {
        /**
         * @var array{
         *     data: list<T>,
         *     meta: array<mixed>,
         *     rows: int,
         *     rows_before_limit_at_least?: int,
         *     statistics: array{elapsed: float, rows_read: int, bytes_read: int}
         * } $contents
         */
        $contents                     = json_decode($contentsJson, true, flags: JSON_THROW_ON_ERROR);
        $this->data                   = $contents['data'];
        $this->meta                   = $contents['meta'];
        $this->rows                   = $contents['rows'];
        $this->rowsBeforeLimitAtLeast = $contents['rows_before_limit_at_least'] ?? null;
        $this->statistics             = $contents['statistics'];
    }
}
