<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use RuntimeException;
use SensitiveParameter;

use function fclose;
use function fread;
use function fwrite;
use function is_resource;
use function ord;
use function pack;
use function strlen;
use function substr;

/** @internal */
final class BinaryStream
{
    /** @param resource $stream */
    public function __construct(private mixed $stream)
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Expected an open native protocol stream.');
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function read(int $length): string
    {
        if ($length < 0 || $length > 67108864) {
            throw new RuntimeException('Native protocol read exceeds the 64 MiB limit.');
        }

        $bytes     = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = @fread($this->stream, $remaining);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Native protocol read failed: timeout or closed connection.');
            }

            $bytes     .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $bytes;
    }

    public function write(
        #[SensitiveParameter]
        string $bytes,
    ): void {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = @fwrite($this->stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Native protocol write failed: timeout or closed connection.');
            }

            $offset += $written;
        }
    }

    public function readVarUInt(): int
    {
        $value = 0;
        for ($shift = 0; $shift <= 56; $shift += 7) {
            $byte   = ord($this->read(1));
            $value |= ($byte & 127) << $shift;
            if (($byte & 128) === 0) {
                return $value;
            }
        }

        throw new RuntimeException('Native protocol VarUInt exceeds PHP_INT_MAX.');
    }

    public function writeVarUInt(int $value): void
    {
        if ($value < 0) {
            throw new RuntimeException('Native protocol VarUInt must not be negative.');
        }

        $bytes = '';
        do {
            $byte    = $value & 127;
            $value >>= 7;
            $bytes  .= pack('C', $byte | ($value > 0 ? 128 : 0));
        } while ($value > 0);

        $this->write($bytes);
    }

    public function readString(int $maxBytes = 67108864): string
    {
        $length = $this->readVarUInt();
        if ($length > $maxBytes) {
            throw new RuntimeException('Native protocol string exceeds its size limit.');
        }

        return $this->read($length);
    }

    public function writeString(
        #[SensitiveParameter]
        string $value,
    ): void {
        if (strlen($value) > 67108864) {
            throw new RuntimeException('Native protocol string exceeds the 64 MiB limit.');
        }

        $this->writeVarUInt(strlen($value));
        $this->write($value);
    }

    public function close(): void
    {
        if (! is_resource($this->stream)) {
            return;
        }

        fclose($this->stream);
    }
}
