<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Generator;

use function json_decode;
use function strpos;
use function substr;
use function trim;

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
            $offset = 0;

            while (true) {
                $lineEnd = strpos($contentsJson, "\n", $offset);
                $line    = $lineEnd === false
                    ? substr($contentsJson, $offset)
                    : substr($contentsJson, $offset, $lineEnd - $offset);

                if (trim($line) === '') {
                    if ($lineEnd === false) {
                        return;
                    }

                    $offset = $lineEnd + 1;

                    continue;
                }

                /** @phpstan-var T $row */
                $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                yield $row;

                if ($lineEnd === false) {
                    return;
                }

                $offset = $lineEnd + 1;
            }
        })();
    }
}
