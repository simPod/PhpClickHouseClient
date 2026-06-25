<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Generator;
use JsonException;

use function explode;
use function json_decode;
use function rtrim;

use const JSON_THROW_ON_ERROR;

/**
 * @template T
 * @implements Output<T>
 */
final readonly class JsonEachRow implements Output
{
    /** @phpstan-var Generator<int, T> */
    public Generator $data;

    public function __construct(string $contentsJson)
    {
        $this->data = self::decodeRows($contentsJson);
    }

    /**
     * @phpstan-return Generator<int, T>
     *
     * @throws JsonException
     */
    private static function decodeRows(string $contentsJson): Generator
    {
        foreach (explode("\n", $contentsJson) as $line) {
            $line = rtrim($line, "\r");

            if ($line === '') {
                continue;
            }

            /** @phpstan-var T $row */
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield $row;
        }
    }
}
