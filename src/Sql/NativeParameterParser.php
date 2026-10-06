<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use RuntimeException;

use function preg_last_error_msg;
use function preg_match;
use function strlen;
use function strpos;
use function strspn;
use function substr;

/** @internal */
final class NativeParameterParser
{
    private const string WordCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    /** @return array<string, Type> */
    public static function parse(string $sql): array
    {
        $types            = [];
        $length           = strlen($sql);
        $offset           = 0;
        $heredocPositions = null;
        $heredocCursors   = [];
        while ($offset < $length) {
            $char = $sql[$offset];
            if ($char === "'" || $char === '"' || $char === '`') {
                $offset = self::skipQuoted($sql, $offset, $char);

                continue;
            }

            if ($char === "\xE2") {
                $quote = substr($sql, $offset, 3);
                if ($quote === '‘' || $quote === '“') {
                    $end    = strpos($sql, $quote === '‘' ? '’' : '”', $offset + 3);
                    $offset = $end === false ? $length : $end + 3;

                    continue;
                }
            }

            $prefix = substr($sql, $offset, 2);
            if ($prefix === '--' || $prefix === '//' || $prefix === '# ' || $prefix === '#!') {
                $end    = strpos($sql, "\n", $offset + 2);
                $offset = $end === false ? $length : $end + 1;

                continue;
            }

            if ($prefix === '/*') {
                $offset = self::skipComment($sql, $offset);

                continue;
            }

            if ($char === '$') {
                $tagEnd = $offset + 1 + strspn($sql, self::WordCharacters, $offset + 1);
                if ($tagEnd < $length && $sql[$tagEnd] === '$') {
                    $delimiter          = substr($sql, $offset, $tagEnd - $offset + 1);
                    $heredocPositions ??= self::indexHeredocDelimiters($sql);
                    $positions          = $heredocPositions[$delimiter];
                    $cursor             = $heredocCursors[$delimiter] ?? 0;
                    while (isset($positions[$cursor]) && $positions[$cursor] <= $tagEnd) {
                        ++$cursor;
                    }

                    $heredocCursors[$delimiter] = $cursor;
                    if (isset($positions[$cursor])) {
                        $offset = $positions[$cursor] + strlen($delimiter);

                        continue;
                    }
                }
            }

            if ($char === '{') {
                $matched = preg_match(
                    '~\G\{([a-zA-Z\d_]+)\s*:\s*([a-zA-Z\d ]+(?:\([^{}]*\))*)\s*}~',
                    $sql,
                    $matches,
                    offset: $offset,
                );
                if ($matched === false) {
                    throw new RuntimeException('Failed to parse native query parameter: ' . preg_last_error_msg());
                }

                if ($matched === 1) {
                    $types[$matches[1]] = Type::fromString($matches[2]);
                    $offset            += strlen($matches[0]);

                    continue;
                }
            }

            // Dollar signs can belong to bare identifiers; do not start a heredoc halfway through one.
            $wordLength = strspn($sql, self::WordCharacters . '$', $offset);
            $offset    += $wordLength > 0 ? $wordLength : 1;
        }

        return $types;
    }

    /** @return array<string, list<int>> */
    private static function indexHeredocDelimiters(string $sql): array
    {
        // Index overlapping delimiters once so distinct dollar-bearing identifiers do not rescan the whole SQL.
        $positions = [];
        $length    = strlen($sql);
        $offset    = strpos($sql, '$');
        while ($offset !== false) {
            $tagEnd = $offset + 1 + strspn($sql, self::WordCharacters, $offset + 1);
            if ($tagEnd < $length && $sql[$tagEnd] === '$') {
                $delimiter               = substr($sql, $offset, $tagEnd - $offset + 1);
                $positions[$delimiter][] = $offset;
            }

            $offset = strpos($sql, '$', $offset + 1);
        }

        return $positions;
    }

    private static function skipQuoted(string $sql, int $offset, string $quote): int
    {
        $length = strlen($sql);
        ++$offset;
        while ($offset < $length) {
            $char = $sql[$offset++];
            if ($char === '\\') {
                ++$offset;
            } elseif ($char === $quote) {
                if ($offset >= $length || $sql[$offset] !== $quote) {
                    break;
                }

                ++$offset;
            }
        }

        return $offset;
    }

    private static function skipComment(string $sql, int $offset): int
    {
        $length  = strlen($sql);
        $offset += 2;
        $depth   = 1;
        while ($offset < $length && $depth > 0) {
            $prefix = substr($sql, $offset, 2);
            if ($prefix === '/*') {
                ++$depth;
                $offset += 2;
            } elseif ($prefix === '*/') {
                --$depth;
                $offset += 2;
            } else {
                ++$offset;
            }
        }

        return $offset;
    }
}
