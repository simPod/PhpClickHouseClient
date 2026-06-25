<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Generator;
use JsonException;

use function explode;
use function iterator_to_array;
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
        /** @phpstan-var list<T> $contents */
        $contents = iterator_to_array(self::decodeRows($contentsJson), preserve_keys: false);

        $this->data = $contents;
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
