<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Generator;

use function explode;
use function json_decode;

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
        // Decoding happens during generator iteration, not while constructing this output object.
        // @phpstan-ignore-next-line missingType.checkedException
        $this->data = (static function () use ($contentsJson): Generator {
            foreach (explode("\n", $contentsJson) as $line) {
                if ($line === '') {
                    continue;
                }

                /** @phpstan-var T $row */
                $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                yield $row;
            }
        })();
    }
}
