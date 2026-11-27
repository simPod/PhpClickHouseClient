<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use RuntimeException;

use function array_is_list;
use function array_slice;
use function chr;
use function count;
use function in_array;
use function intdiv;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function ord;
use function pack;
use function preg_match;
use function str_repeat;
use function strcmp;
use function strlen;
use function substr;
use function trim;
use function unpack;

use const PHP_INT_MAX;

/** @internal */
final class TypeCodec
{
    private const int MaxRows  = 1000000;
    private const int MaxBytes = 67108864;
    private const int MaxDepth = 32;

    /** @return list<mixed> */
    public function read(BinaryStream $stream, string $type, int $rows, DecodeBudget|null $budget = null): array
    {
        $this->validateRows($rows);

        return $this->readValues($stream, $type, $rows, 0, $budget ?? new DecodeBudget());
    }

    /** @param array<array-key, mixed> $values */
    public function write(BinaryStream $stream, string $type, array $values): void
    {
        if (! array_is_list($values)) {
            throw new RuntimeException('Native column values must be a list for type ' . $type . '.');
        }

        $this->writeValues($stream, $type, $values, 0);
    }

    /** @return list<mixed> */
    private function readValues(BinaryStream $stream, string $type, int $rows, int $depth, DecodeBudget $budget): array
    {
        $this->validateDepth($depth);
        $budget->consume($rows * 128);
        $spec = $this->parse($type);
        if ($spec['kind'] === 'Nullable') {
            $map = $stream->read($rows);
            for ($row = 0; $row < $rows; $row++) {
                if (ord($map[$row]) > 1) {
                    throw new RuntimeException('Invalid native null map for type ' . $type . '.');
                }
            }

            $values = $this->readValues($stream, $spec['arguments'][0], $rows, $depth + 1, $budget);
            foreach ($values as $row => $value) {
                if ($map[$row] === "\x01") {
                    $values[$row] = null;
                }
            }

            return $values;
        }

        if ($spec['kind'] === 'Array') {
            $offsets = [];
            $last    = 0;
            for ($row = 0; $row < $rows; $row++) {
                $offset = $this->readInteger($stream->read(8), 'UInt64');
                if (! is_int($offset) || $offset < $last || $offset > self::MaxRows) {
                    throw new RuntimeException('Invalid native array offset for type ' . $type . '.');
                }

                $offsets[] = $offset;
                $last      = $offset;
            }

            $nested = $this->readValues($stream, $spec['arguments'][0], $last, $depth + 1, $budget);
            $values = [];
            $start  = 0;
            foreach ($offsets as $offset) {
                $values[] = array_slice($nested, $start, $offset - $start);
                $start    = $offset;
            }

            return $values;
        }

        if ($spec['kind'] === 'Tuple') {
            $values = [];
            for ($row = 0; $row < $rows; $row++) {
                $values[] = [];
            }

            foreach ($spec['arguments'] as $argument) {
                foreach ($this->readValues($stream, $argument, $rows, $depth + 1, $budget) as $row => $value) {
                    $values[$row][] = $value;
                }
            }

            return $values;
        }

        $values = [];
        if ($spec['kind'] === 'String') {
            $size = 0;
            for ($row = 0; $row < $rows; $row++) {
                $length = $stream->readVarUInt();
                $size  += $length;
                if ($size > self::MaxBytes) {
                    throw new RuntimeException('Native column exceeds the 64 MiB limit for type ' . $type . '.');
                }

                $budget->consume($length);
                $values[] = $stream->read($length);
            }

            return $values;
        }

        if ($rows * $spec['width'] > self::MaxBytes) {
            throw new RuntimeException('Native column exceeds the 64 MiB limit for type ' . $type . '.');
        }

        $budget->consume($rows * $spec['width']);
        $bytes = $stream->read($rows * $spec['width']);
        for ($row = 0; $row < $rows; $row++) {
            $value    = substr($bytes, $row * $spec['width'], $spec['width']);
            $values[] = match ($spec['kind']) {
                'FixedString' => $value,
                'Bool' => ord($value) !== 0,
                'Float32', 'Float64' => $this->readFloat($value, $spec['kind']),
                'Enum8', 'Enum16' => $this->readInteger($value, $spec['width'] === 1 ? 'Int8' : 'Int16'),
                default => $this->readInteger($value, $spec['kind']),
            };
        }

        return $values;
    }

    /** @param list<mixed> $values */
    private function writeValues(BinaryStream $stream, string $type, array $values, int $depth): void
    {
        $this->validateDepth($depth);
        $this->validateRows(count($values));
        $spec = $this->parse($type);
        if ($spec['kind'] === 'Enum8' || $spec['kind'] === 'Enum16') {
            $indexes = $this->enumIndexes($spec['arguments']);
            foreach ($values as $value) {
                if (! is_int($value) || ! in_array($value, $indexes, true)) {
                    throw new RuntimeException('Expected a declared numeric index for native type ' . $type . '.');
                }

                $stream->write($this->writeInteger($value, $spec['width'] === 1 ? 'Int8' : 'Int16', $spec['width']));
            }

            return;
        }

        if ($spec['kind'] === 'Nullable') {
            $map     = '';
            $nested  = [];
            $default = $this->defaultValue($spec['arguments'][0], $depth + 1);
            foreach ($values as $value) {
                $map     .= $value === null ? "\x01" : "\x00";
                $nested[] = $value ?? $default;
            }

            $stream->write($map);
            $this->writeValues($stream, $spec['arguments'][0], $nested, $depth + 1);

            return;
        }

        if ($spec['kind'] === 'Array') {
            $nested = [];
            $offset = 0;
            foreach ($values as $value) {
                if (! is_array($value) || ! array_is_list($value)) {
                    throw new RuntimeException('Expected a list for native type ' . $type . '.');
                }

                $offset += count($value);
                $this->validateRows($offset);
                $stream->write(pack('P', $offset));
                foreach ($value as $element) {
                    $nested[] = $element;
                }
            }

            $this->writeValues($stream, $spec['arguments'][0], $nested, $depth + 1);

            return;
        }

        if ($spec['kind'] === 'Tuple') {
            $tuples = [];
            foreach ($values as $value) {
                if (! is_array($value) || ! array_is_list($value) || count($value) !== count($spec['arguments'])) {
                    throw new RuntimeException('Invalid tuple value for native type ' . $type . '.');
                }

                $tuples[] = $value;
            }

            foreach ($spec['arguments'] as $index => $argument) {
                $nested = [];
                foreach ($tuples as $tuple) {
                    $nested[] = $tuple[$index];
                }

                $this->writeValues($stream, $argument, $nested, $depth + 1);
            }

            return;
        }

        if ($spec['kind'] !== 'String' && count($values) * $spec['width'] > self::MaxBytes) {
            throw new RuntimeException('Native column exceeds the 64 MiB limit for type ' . $type . '.');
        }

        $size = 0;
        foreach ($values as $value) {
            switch ($spec['kind']) {
                case 'String':
                case 'FixedString':
                    if (! is_string($value) || ($spec['kind'] === 'FixedString' && strlen($value) > $spec['width'])) {
                        throw new RuntimeException('Invalid string value for native type ' . $type . '.');
                    }

                    if ($spec['kind'] === 'String') {
                        $size += strlen($value);
                        if ($size > self::MaxBytes) {
                            throw new RuntimeException(
                                'Native column exceeds the 64 MiB limit for type ' . $type . '.',
                            );
                        }

                        $stream->writeString($value);
                    } else {
                        $stream->write($value . str_repeat("\x00", $spec['width'] - strlen($value)));
                    }

                    break;
                case 'Bool':
                    if (! is_bool($value)) {
                        throw new RuntimeException('Expected a boolean for native type ' . $type . '.');
                    }

                    $stream->write($value ? "\x01" : "\x00");

                    break;
                case 'Float32':
                case 'Float64':
                    if (! is_int($value) && ! is_float($value)) {
                        throw new RuntimeException('Expected a number for native type ' . $type . '.');
                    }

                    $bytes = pack($spec['kind'] === 'Float32' ? 'g' : 'e', $value);
                    if (is_finite((float) $value) && ! is_finite($this->readFloat($bytes, $spec['kind']))) {
                        throw new RuntimeException('Float value is out of range for native type ' . $type . '.');
                    }

                    $stream->write($bytes);

                    break;
                default:
                    $stream->write($this->writeInteger($value, $spec['kind'], $spec['width']));
            }
        }
    }

    /** @phpstan-return array{kind: string, arguments: list<string>, width: int} */
    private function parse(string $type): array
    {
        if (strlen($type) > 4096) {
            throw new RuntimeException('Native type declaration exceeds its size limit.');
        }

        $type          = trim($type);
        $kind          = $type;
        $arguments     = [];
        $parameterized = preg_match('~^([A-Za-z][A-Za-z0-9]*)\((.*)\)$~sD', $type, $matches) === 1;
        if ($parameterized) {
            $kind      = $matches[1];
            $arguments = $this->splitArguments($matches[2], $type);
        }

        $width = 0;
        if (preg_match('~^(U?Int)(8|16|32|64)$~D', $kind, $matches) === 1 && ! $parameterized) {
            $width = intdiv((int) $matches[2], 8);
        } elseif ($kind === 'String' && ! $parameterized) {
            $width = 0;
        } elseif ($kind === 'Bool' && ! $parameterized) {
            $width = 1;
        } elseif (($kind === 'Float32' || $kind === 'Float64') && ! $parameterized) {
            $width = $kind === 'Float32' ? 4 : 8;
        } elseif ($kind === 'Date' && ! $parameterized) {
            $kind  = 'UInt16';
            $width = 2;
        } elseif ($kind === 'Date32' && ! $parameterized) {
            $kind  = 'Int32';
            $width = 4;
        } elseif (
            $kind === 'DateTime'
            && (! $parameterized || (count($arguments) === 1 && $this->isTimezone($arguments[0])))
        ) {
            $kind  = 'UInt32';
            $width = 4;
        } elseif (
            $kind === 'DateTime64'
            && (count($arguments) === 1 || count($arguments) === 2)
            && preg_match('~^[0-9]$~D', $arguments[0]) === 1
            && (count($arguments) === 1 || $this->isTimezone($arguments[1]))
        ) {
            $kind  = 'Int64';
            $width = 8;
        } elseif (
            $kind === 'FixedString'
            && count($arguments) === 1
            && preg_match('~^[1-9][0-9]{0,7}$~D', $arguments[0]) === 1
            && (int) $arguments[0] <= self::MaxBytes
        ) {
            $width = (int) $arguments[0];
        } elseif (($kind === 'Nullable' || $kind === 'Array') && count($arguments) === 1) {
            $width = 0;
        } elseif ($kind === 'Tuple' && count($arguments) <= 1024 && $parameterized) {
            $width = 0;
        } elseif (($kind === 'Enum8' || $kind === 'Enum16') && $parameterized) {
            // ProfileEvents uses Enum8. Expose its signed index without changing its wire representation.
            $width = $kind === 'Enum8' ? 1 : 2;
            foreach ($this->enumIndexes($arguments) as $index) {
                $this->writeInteger($index, $width === 1 ? 'Int8' : 'Int16', $width);
            }
        } else {
            throw new RuntimeException('Unsupported native type ' . $type . '.');
        }

        return ['kind' => $kind, 'arguments' => $arguments, 'width' => $width];
    }

    /** @return list<string> */
    private function splitArguments(string $parameters, string $type): array
    {
        if ($parameters === '') {
            return [];
        }

        $arguments = [];
        $depth     = 0;
        $quoted    = false;
        $start     = 0;
        for ($index = 0; $index < strlen($parameters); $index++) {
            $character = $parameters[$index];
            if ($quoted && $character === '\\') {
                ++$index;

                continue;
            }

            if ($character === "'") {
                $quoted = ! $quoted;
            } elseif (! $quoted) {
                if ($character === '(') {
                    $depth++;
                    $this->validateDepth($depth);
                } elseif ($character === ')') {
                    $depth--;
                    if ($depth < 0) {
                        throw new RuntimeException('Invalid native type ' . $type . '.');
                    }
                } elseif ($character === ',' && $depth === 0) {
                    $arguments[] = trim(substr($parameters, $start, $index - $start));
                    $start       = $index + 1;
                }
            }
        }

        if ($quoted || $depth !== 0) {
            throw new RuntimeException('Invalid native type ' . $type . '.');
        }

        $arguments[] = trim(substr($parameters, $start));
        foreach ($arguments as $argument) {
            if ($argument === '') {
                throw new RuntimeException('Invalid native type ' . $type . '.');
            }
        }

        return $arguments;
    }

    private function isTimezone(string $argument): bool
    {
        return preg_match("~^'[A-Za-z0-9_+./:-]+'$~D", $argument) === 1;
    }

    private function defaultValue(string $type, int $depth): mixed
    {
        $this->validateDepth($depth);
        $spec = $this->parse($type);
        if ($spec['kind'] === 'Tuple') {
            $values = [];
            foreach ($spec['arguments'] as $argument) {
                $values[] = $this->defaultValue($argument, $depth + 1);
            }

            return $values;
        }

        return match ($spec['kind']) {
            'Nullable' => null,
            'Array' => [],
            'String', 'FixedString' => '',
            'Bool' => false,
            'Float32', 'Float64' => 0.0,
            'Enum8', 'Enum16' => $this->enumIndexes($spec['arguments'])[0],
            default => 0,
        };
    }

    /**
     * @param list<string> $arguments
     *
     * @return non-empty-list<int>
     */
    private function enumIndexes(array $arguments): array
    {
        $indexes = [];
        foreach ($arguments as $argument) {
            if (preg_match("~^'(?:[^'\\\\]|\\\\.)*'\\s*=\\s*(-?[0-9]+)$~sD", $argument, $matches) !== 1) {
                throw new RuntimeException('Invalid native enum declaration.');
            }

            $indexes[] = (int) $matches[1];
        }

        if ($indexes === []) {
            throw new RuntimeException('Native enum must declare at least one value.');
        }

        return $indexes;
    }

    private function readFloat(string $bytes, string $kind): float
    {
        /** @phpstan-var array{value: float} $value */
        $value = unpack($kind === 'Float32' ? 'gvalue' : 'evalue', $bytes);

        return $value['value'];
    }

    private function readInteger(string $bytes, string $kind): int|string
    {
        $width = strlen($bytes);
        if ($width === 8) {
            /** @phpstan-var array{low: int, high: int} $parts */
            $parts = unpack('Vlow/Vhigh', $bytes);
            if ($kind === 'UInt64' && $parts['high'] >= 2147483648) {
                return $this->unsignedDecimal($bytes);
            }

            $high = $parts['high'];
            if ($kind === 'Int64' && $high >= 2147483648) {
                $high -= 4294967296;
            }

            return $high * 4294967296 + $parts['low'];
        }

        /** @phpstan-var array{value: int} $parts */
        $parts = unpack(match ($width) {
            1 => 'Cvalue',
            2 => 'vvalue',
            default => 'Vvalue',
        }, $bytes);
        $value = $parts['value'];
        if ($kind[0] === 'I' && $value >= 1 << ($width * 8 - 1)) {
            $value -= 1 << $width * 8;
        }

        return $value;
    }

    private function writeInteger(mixed $value, string $kind, int $width): string
    {
        if ($kind === 'UInt64' && is_string($value)) {
            if (
                preg_match('~^(0|[1-9][0-9]{0,19})$~D', $value) !== 1
                || (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0)
            ) {
                throw new RuntimeException('Integer value is out of range for native type ' . $kind . '.');
            }

            return $this->decimalBytes($value);
        }

        if (! is_int($value)) {
            throw new RuntimeException('Expected an integer for native type ' . $kind . '.');
        }

        $signed = $kind[0] === 'I';
        $min    = $signed ? ($width === 8 ? -PHP_INT_MAX - 1 : -(1 << ($width * 8 - 1))) : 0;
        $max    = $width === 8 ? PHP_INT_MAX : ($signed ? (1 << ($width * 8 - 1)) - 1 : (1 << $width * 8) - 1);
        if ($value < $min || $value > $max) {
            throw new RuntimeException('Integer value is out of range for native type ' . $kind . '.');
        }

        return pack(match ($width) {
            1 => 'C',
            2 => 'v',
            4 => 'V',
            default => 'P',
        }, $value);
    }

    private function unsignedDecimal(string $bytes): string
    {
        $decimal = '0';
        for ($byte = 7; $byte >= 0; $byte--) {
            $carry  = ord($bytes[$byte]);
            $result = '';
            for ($digit = strlen($decimal) - 1; $digit >= 0; $digit--) {
                $carry += (ord($decimal[$digit]) - 48) * 256;
                $result = chr(48 + $carry % 10) . $result;
                $carry  = intdiv($carry, 10);
            }

            while ($carry > 0) {
                $result = chr(48 + $carry % 10) . $result;
                $carry  = intdiv($carry, 10);
            }

            $decimal = $result;
        }

        return $decimal;
    }

    private function decimalBytes(string $decimal): string
    {
        $bytes = '';
        for ($byte = 0; $byte < 8; $byte++) {
            $carry    = 0;
            $quotient = '';
            for ($digit = 0; $digit < strlen($decimal); $digit++) {
                $carry   = $carry * 10 + ord($decimal[$digit]) - 48;
                $current = intdiv($carry, 256);
                if ($current !== 0 || $quotient !== '') {
                    $quotient .= (string) $current;
                }

                $carry %= 256;
            }

            $bytes  .= pack('C', $carry);
            $decimal = $quotient;
        }

        return $bytes;
    }

    private function validateDepth(int $depth): void
    {
        if ($depth > self::MaxDepth) {
            throw new RuntimeException('Native type nesting exceeds its depth limit.');
        }
    }

    private function validateRows(int $rows): void
    {
        if ($rows < 0 || $rows > self::MaxRows) {
            throw new RuntimeException('Native column exceeds its row limit.');
        }
    }
}
