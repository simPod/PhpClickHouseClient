<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Output;

use Generator;
use JsonException;
use Psr\Http\Message\StreamInterface;
use SimPod\ClickHouseClient\Exception\ServerError;

use function explode;
use function implode;
use function json_decode;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * @template T
 * @implements Output<T>
 */
final readonly class JsonEachRow implements Output
{
    /** @phpstan-var Generator<int, T> */
    public Generator $data;

    /** @throws JsonException|ServerError */
    public function __construct(string|StreamInterface $contentsJson)
    {
        $lines = $contentsJson instanceof StreamInterface
            ? self::readStreamLines($contentsJson)
            : explode("\n", $contentsJson);

        // Decoding happens during generator iteration, not while constructing this output object.
        $this->data = self::decodeLines($lines);
    }

    /**
     * @param iterable<string> $lines
     *
     * @return Generator<int, T>
     *
     * @throws JsonException|ServerError
     */
    private static function decodeLines(iterable $lines): Generator
    {
        $streamedExceptionLines = [];

        foreach ($lines as $line) {
            if ($streamedExceptionLines !== []) {
                $streamedExceptionLines[] = $line;

                continue;
            }

            if ($line === '') {
                continue;
            }

            if ($line === '__exception__') {
                $streamedExceptionLines[] = $line;

                continue;
            }

            /** @phpstan-var T $row */
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield $row;
        }

        if ($streamedExceptionLines !== []) {
            throw ServerError::fromResponseContent(implode("\n", $streamedExceptionLines), 200);
        }
    }

    /** @return Generator<string> */
    private static function readStreamLines(StreamInterface $stream): Generator
    {
        $buffer = '';
        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);

            while (($position = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $position);

                $buffer = substr($buffer, $position + 1);
            }
        }

        if ($buffer === '') {
            return;
        }

        yield $buffer;
    }
}
