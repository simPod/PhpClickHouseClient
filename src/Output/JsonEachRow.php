<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use JsonException;

use function explode;
use function json_decode;
use function rtrim;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-immutable
 * @template T
 * @implements Output<T>
 */
final readonly class JsonEachRow implements Output
{
    /** @var list<T> */
    public array $data;

    /** @throws JsonException */
    public function __construct(string $contentsJson)
    {
        $contents = [];

        foreach (explode("\n", $contentsJson) as $line) {
            $line = rtrim($line, "\r");

            if ($line === '') {
                continue;
            }

            /** @var T $row */
            $row        = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $contents[] = $row;
        }

        /** @var list<T> $contents */
        $this->data = $contents;
    }
}
