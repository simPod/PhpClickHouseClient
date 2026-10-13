<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Native;

use RuntimeException;

use function bin2hex;
use function chr;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function ord;
use function pack;
use function stream_select;
use function stream_set_timeout;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;
use function strlen;
use function strrchr;
use function substr;

use const STDOUT;

/** Independent, uncompressed revision-54459 wire fixtures, not production codec output. */
final class ScriptedNativeServer
{
    private const string BlockInfo = "\x01\x00\x02\xff\xff\xff\xff\x00";

    /** @var resource */
    private $listener;

    /** @var resource */
    private $socket;

    public function __construct()
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        if ($listener === false) {
            throw new RuntimeException('Cannot start scripted native server.');
        }

        $this->listener = $listener;
        $address        = stream_socket_get_name($listener, false);
        if ($address === false) {
            throw new RuntimeException('Cannot read scripted server address.');
        }

        $port = strrchr($address, ':');
        if ($port === false) {
            throw new RuntimeException('Scripted server address has no port.');
        }

        fwrite(STDOUT, substr($port, 1) . "\n");
    }

    public function run(string $scenario): void
    {
        $this->accept();
        switch ($scenario) {
            case 'select':
                $this->query(
                    'SELECT {label:String}, {missing:Nullable(String)}, '
                        . '{kind:Enum8(\'a\\\\t\' = 1)}, {_request_body:String}, 9',
                    ['max_block_size' => [1, '2'], 'max_threads' => [1, '1']],
                    [
                        'label' => [2, "'a\\'b\\\\\\\\c'"],
                        'missing' => [2, "'\\\\N'"],
                        'kind' => [2, "'a\\\\\\\\t'"],
                        '_request_body' => [2, "'raw\\\\t'"],
                    ],
                );
                $this->send($this->block(1, 0, '', ''));
                $this->send($this->block(1, 2, "\x07\x09", "\x03one\x03two"));
                $this->send("\x03\x02\x08\x03\x00\x00"); // Progress: rows, bytes, total rows, written rows/bytes.
                $this->send("\x06\x02\x01\x08\x00\x02\x01"); // ProfileInfo.
                $this->send($this->profileEvents());
                $this->send($this->block(1, 1, "\x0b", "\x05three"));
                $this->send($this->block(7, 1, "\x1b", "\x05total"));
                $this->send($this->block(8, 2, "\x07\x0b", "\x03min\x03max"));
                $this->send($this->emptyBlock(1) . "\x05");
                $this->query('SELECT empty', ['max_block_size' => [1, '100'], 'max_threads' => [1, '1']]);
                $this->send("\x01\x00" . self::BlockInfo . "\x01\x00\x05empty\x06UInt16\x00"
                    . $this->emptyBlock(1) . "\x05");

                break;
            case 'insert':
                $this->query('INSERT INTO `events` (`label`,`id`) VALUES');
                $this->send($this->insertBlock(1, 0, '', ''));
                foreach (
                    [
                        $this->insertBlock(2, 2, "\x03one\x03two", "\x07\x09"),
                        $this->insertBlock(2, 1, "\x05three", "\x0b"),
                        $this->emptyBlock(2),
                    ] as $block
                ) {
                    $this->expect($block);
                    $read   = [$this->socket];
                    $write  = [];
                    $except = [];
                    if (stream_select($read, $write, $except, 0, 100000) !== 0) {
                        throw new RuntimeException(
                            'Client sent another block before its ProfileEvents acknowledgement.',
                        );
                    }

                    $this->send("\x03\x01\x01\x00\x01\x01" . $this->profileEvents());
                }

                $this->send("\x05");
                $this->query('SELECT inserted');
                $this->send($this->block(1, 1, "\x03", "\x04done") . "\x05");

                break;
            case 'exception':
            case 'truncated':
                $this->query('SELECT failing');
                $this->send($this->block(1, 1, "\x07", "\x07partial"));
                if ($scenario === 'exception') {
                    $this->send("\x02" . pack('V', 241) . $this->string('MEMORY_LIMIT_EXCEEDED')
                        . $this->string('Query exceeded its memory limit') . $this->string('server.cpp:42') . "\x00");
                    $this->expectDisconnect();
                } else {
                    // The next String promises five bytes, but only two arrive before EOF.
                    $this->send($this->block(1, 1, "\x08", "\x05ab"));
                }

                $this->reconnect();

                break;
            case 'budget':
                $this->query('SELECT failing');
                for ($i = 0; $i < 5; ++$i) {
                    $this->send("\x01\x00" . self::BlockInfo . "\x01" . $this->varUInt(40000)
                        . $this->string('empty') . $this->string('Tuple()') . "\x00");
                }

                $this->expectDisconnect();
                $this->reconnect();

                break;
            case 'invalid-insert':
                $this->query('INSERT INTO `events` (`label`,`id`) VALUES');
                $this->send($this->insertBlock(1, 0, '', ''));
                $this->expectDisconnect();
                $this->reconnect();

                break;
            default:
                throw new RuntimeException('Unknown scripted native server scenario.');
        }

        $this->expectDisconnect();
        fclose($this->socket);
        fclose($this->listener);
    }

    private function reconnect(): void
    {
        fclose($this->socket);
        $this->accept();
        $this->query('SELECT recovered');
        $this->send($this->block(1, 1, "\x2a", "\x05fresh") . "\x05");
    }

    private function accept(): void
    {
        $socket = stream_socket_accept($this->listener, 5);
        if ($socket === false) {
            throw new RuntimeException('Client did not connect.');
        }

        $this->socket = $socket;
        stream_set_timeout($socket, 5);
        $this->expect("\x00"); // Client Hello.
        $this->readString(); // Client name.
        $this->readVarUInt(); // Major version.
        $this->readVarUInt(); // Minor version.
        $this->equal(54459, $this->readVarUInt());
        $this->equal('analytics', $this->readString());
        $this->equal('reader', $this->readString());
        $this->equal('test-password', $this->readString());
        // Server revision can be newer; only fields at the advertised client revision follow.
        $this->send("\x00" . $this->string('ClickHouse') . "\x19\x01" . $this->varUInt(54465)
            . $this->string('UTC') . $this->string('scripted') . "\x01");
        $this->expect("\x00"); // Handshake Addendum quota key, before the first query.
    }

    /**
     * @phpstan-param array<string, array{int, string}> $settings
     * @phpstan-param array<string, array{int, string}> $parameters
     */
    private function query(string $sql, array $settings = [], array $parameters = []): void
    {
        $this->expect("\x01");
        if ($this->readString() === '') {
            throw new RuntimeException('Query id must not be empty.');
        }

        $this->expect("\x01"); // Initial-query ClientInfo.
        $this->readString(); // Initial user.
        $this->readString(); // Initial query id.
        $this->readString(); // Initial address.
        $this->read(8); // Initial query time, UInt64.
        $this->expect("\x01"); // TCP interface.
        $this->readString(); // OS user.
        $this->readString(); // Hostname.
        $this->readString(); // Client name.
        $this->readVarUInt(); // Major version.
        $this->readVarUInt(); // Minor version.
        $this->equal(54459, $this->readVarUInt());
        $this->readString(); // Quota key.
        $this->readVarUInt(); // Distributed depth.
        $this->readVarUInt(); // Patch version.
        $this->expect("\x00\x00\x00\x00"); // No trace context or parallel replicas.
        $this->equal($settings, $this->readSettings());
        $this->expect("\x00\x02\x00"); // Empty inter-server secret, complete stage, no compression.
        $this->equal($sql, $this->readString());
        $this->equal($parameters, $this->readSettings());
        $this->expect($this->emptyBlock(2)); // End of external tables, also required before INSERT schema.
    }

    /** @phpstan-return array<string, array{int, string}> */
    private function readSettings(): array
    {
        $settings = [];
        while (($name = $this->readString()) !== '') {
            $settings[$name] = [$this->readVarUInt(), $this->readString()];
        }

        return $settings;
    }

    /** @param int<0, 127> $packet */
    private function block(int $packet, int $rows, string $ids, string $labels): string
    {
        return chr($packet) . "\x00" . self::BlockInfo . "\x02" . $this->varUInt($rows)
            . "\x02id\x05UInt8\x00" . $ids . "\x05label\x06String\x00" . $labels;
    }

    /** @param int<0, 127> $packet */
    private function insertBlock(int $packet, int $rows, string $labels, string $ids): string
    {
        return chr($packet) . "\x00" . self::BlockInfo . "\x02" . $this->varUInt($rows)
            . "\x05label\x06String\x00" . $labels . "\x02id\x05UInt8\x00" . $ids;
    }

    /** @param int<0, 127> $packet */
    private function emptyBlock(int $packet): string
    {
        return chr($packet) . "\x00" . self::BlockInfo . "\x00\x00";
    }

    private function profileEvents(): string
    {
        return "\x0e\x00" . self::BlockInfo . "\x01\x01" . $this->string('type')
            . $this->string("Enum8('Increment' = 1, 'Gauge' = 2)") . "\x00\x01";
    }

    private function expectDisconnect(): void
    {
        if (fread($this->socket, 1) !== '' || ! feof($this->socket)) {
            throw new RuntimeException('Client did not disconnect, or sent unexpected data.');
        }
    }

    private function expect(string $bytes): void
    {
        $actual = $this->read(strlen($bytes));
        if ($actual !== $bytes) {
            throw new RuntimeException('Expected wire bytes ' . bin2hex($bytes) . ', received ' . bin2hex($actual));
        }
    }

    private function equal(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Unexpected query, setting, parameter, or handshake field.');
        }
    }

    private function send(string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($this->socket, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Cannot write scripted server response.');
            }

            $bytes = substr($bytes, $written);
        }
    }

    private function read(int $size): string
    {
        $bytes     = '';
        $remaining = $size;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Client packet ended early or timed out.');
            }

            $bytes     .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $bytes;
    }

    private function readVarUInt(): int
    {
        $value = 0;
        for ($shift = 0; $shift < 63; $shift += 7) {
            $byte   = ord($this->read(1));
            $value |= ($byte & 127) << $shift;
            if ($byte < 128) {
                return $value;
            }
        }

        throw new RuntimeException('Invalid fixture VarUInt.');
    }

    private function readString(): string
    {
        return $this->read($this->readVarUInt());
    }

    private function varUInt(int $value): string
    {
        $bytes = '';
        do {
            $byte    = $value & 127;
            $value >>= 7;
            $bytes  .= pack('C', $byte | ($value === 0 ? 0 : 128));
        } while ($value !== 0);

        return $bytes;
    }

    private function string(string $value): string
    {
        return $this->varUInt(strlen($value)) . $value;
    }
}
