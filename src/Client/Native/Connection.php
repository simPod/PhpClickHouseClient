<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use Random\RandomException;
use RuntimeException;
use SimPod\ClickHouseClient\Exception\NativeServerError;
use SimPod\ClickHouseClient\Settings\SettingsProvider;
use Throwable;

use function assert;
use function bin2hex;
use function count;
use function is_int;
use function min;
use function ord;
use function pack;
use function random_bytes;
use function str_contains;
use function stream_context_create;
use function stream_set_timeout;
use function stream_socket_client;
use function unpack;

use const STREAM_CLIENT_CONNECT;

/**
 * @internal
 *
 * @phpstan-import-type Settings from SettingsProvider
 */
final class Connection
{
    // Advertise only implemented fields; newer servers negotiate this revision down.
    private const int Revision = 54459;

    private BinaryStream|null $stream = null;

    private bool $queryActive = false;

    private BlockCodec $blockCodec;

    public function __construct(private readonly ConnectionConfig $config)
    {
        $this->blockCodec = new BlockCodec(self::Revision);
    }

    /**
     * @param array<string, string> $parameters
     * @phpstan-param Settings $settings
     */
    public function startQuery(string $sql, array $settings, array $parameters): string
    {
        if ($this->queryActive) {
            throw new RuntimeException('A native TCP connection can run only one query at a time.');
        }

        if (count($settings) > 65536 || count($parameters) > 65536) {
            throw new RuntimeException('Too many native query settings or parameters.');
        }

        // This changes the column framing and is not supported by this client.
        if ((string) ($settings['output_format_native_encode_types_in_binary_format'] ?? 0) !== '0') {
            throw new RuntimeException('Binary-encoded native type names are not supported.');
        }

        $this->connect();
        $stream = $this->stream;
        assert($stream !== null);
        try {
            $id = bin2hex(random_bytes(16));
        } catch (RandomException $error) {
            throw new RuntimeException('Cannot generate a native query identifier.', 0, $error);
        }

        try {
            $this->queryActive = true;
            $stream->writeVarUInt(1); // Query
            $stream->writeString($id);
            $this->writeClientInfo($stream);
            foreach ($settings as $name => $value) {
                $stream->writeString($name);
                $stream->writeVarUInt(1); // Important: do not silently ignore unknown settings.
                $stream->writeString((string) $value);
            }

            $stream->writeString('');
            $stream->writeString(''); // Inter-server secret (unused by external clients).
            $stream->writeVarUInt(2); // Complete query processing stage.
            $stream->writeVarUInt(0); // No compression.
            $stream->writeString($sql);
            foreach ($parameters as $name => $value) {
                $stream->writeString($name);
                $stream->writeVarUInt(2); // Custom setting: query parameter.
                $stream->writeString($value);
            }

            $stream->writeString('');
            // Finish external tables, including for INSERT. Insert rows follow the sample schema.
            $this->sendBlock(new Block());
        } catch (Throwable $error) {
            $this->close();

            throw $error;
        }

        return $id;
    }

    /** @return array{int, Block|null} */
    public function receive(): array
    {
        $stream = $this->stream;
        if ($stream === null || ! $this->queryActive) {
            throw new RuntimeException('No active native TCP query.');
        }

        try {
            $type = $stream->readVarUInt();
            switch ($type) {
                case 1: // Data
                case 7: // Totals
                case 8: // Extremes
                case 10: // Logs
                case 14: // ProfileEvents
                    $stream->readString(); // External table name.

                    return [$type, $this->blockCodec->read($stream)];
                case 2:
                    throw $this->readException($stream);
                case 3: // Progress, revision 54459.
                    for ($i = 0; $i < 5; ++$i) {
                        $stream->readVarUInt();
                    }

                    break;
                case 5: // EndOfStream: only this packet signals successful completion.
                    $this->queryActive = false;

                    break;
                case 6: // ProfileInfo
                    $stream->readVarUInt();
                    $stream->readVarUInt();
                    $stream->readVarUInt();
                    $stream->read(1);
                    $stream->readVarUInt();
                    $stream->read(1);

                    break;
                case 11: // TableColumns
                    $stream->readString();
                    $stream->readString();

                    break;
                default:
                    throw new RuntimeException('Unsupported native server packet: ' . $type);
            }

            return [$type, null];
        } catch (Throwable $error) {
            $this->close();

            throw $error;
        }
    }

    public function sendBlock(Block $block): void
    {
        $stream = $this->stream;
        if ($stream === null || ! $this->queryActive) {
            throw new RuntimeException('No active native TCP query.');
        }

        try {
            $stream->writeVarUInt(2); // Client Data
            $stream->writeString('');
            $this->blockCodec->write($stream, $block);
        } catch (Throwable $error) {
            $this->close();

            throw $error;
        }
    }

    public function close(): void
    {
        $this->stream?->close();
        $this->stream      = null;
        $this->queryActive = false;
    }

    private function connect(): void
    {
        if ($this->stream !== null) {
            return;
        }

        $host = $this->config->host;
        if (str_contains($host, ':') && $host[0] !== '[') {
            $host = '[' . $host . ']';
        }

        $contextOptions = ['socket' => ['tcp_nodelay' => true]];
        if ($this->config->tls) {
            $contextOptions['ssl'] = [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $this->config->serverName ?? $this->config->host,
            ];
            if ($this->config->caFile !== null) {
                $contextOptions['ssl']['cafile'] = $this->config->caFile;
            }
        }

        $socket = @stream_socket_client(
            ($this->config->tls ? 'tls://' : 'tcp://') . $host . ':' . $this->config->port,
            $errorCode,
            $errorMessage,
            $this->config->connectTimeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions),
        );
        if ($socket === false) {
            throw new RuntimeException('Cannot connect to native TCP endpoint: ' . $errorMessage, $errorCode ?? 0);
        }

        $seconds      = (int) $this->config->readTimeout;
        $microseconds = (int) (($this->config->readTimeout - $seconds) * 1000000);
        if (! stream_set_timeout($socket, $seconds, $microseconds)) {
            $stream = new BinaryStream($socket);
            $stream->close();

            throw new RuntimeException('Cannot set native TCP I/O timeout.');
        }

        $this->stream = new BinaryStream($socket);
        try {
            $stream = $this->stream;
            $stream->writeVarUInt(0); // Hello
            $stream->writeString('ClickHouse PHP simpod/clickhouse-client');
            $stream->writeVarUInt(1);
            $stream->writeVarUInt(0);
            $stream->writeVarUInt(self::Revision);
            $stream->writeString($this->config->database);
            $stream->writeString($this->config->user);
            $stream->writeString($this->config->password);

            $packet = $stream->readVarUInt();
            if ($packet === 2) {
                throw $this->readException($stream);
            }

            if ($packet !== 0) {
                throw new RuntimeException('Expected native server Hello.');
            }

            $stream->readString(); // Server name.
            $stream->readVarUInt(); // Major version.
            $stream->readVarUInt(); // Minor version.
            $revision = min(self::Revision, $stream->readVarUInt());
            if ($revision < self::Revision) {
                throw new RuntimeException('Native TCP requires protocol revision 54459 or newer.');
            }

            $stream->readString(); // Timezone (retained in each temporal type where applicable).
            $stream->readString(); // Display name.
            $stream->readVarUInt(); // Patch version.
            $stream->writeString(''); // Handshake Addendum quota key.
        } catch (Throwable $error) {
            $this->close();

            throw $error;
        }
    }

    private function writeClientInfo(BinaryStream $stream): void
    {
        $stream->write("\x01"); // Initial query.
        $stream->writeString(''); // Initial user.
        $stream->writeString(''); // Initial query id.
        $stream->writeString('0.0.0.0:0');
        $stream->write(pack('P', 0)); // Initial query time.
        $stream->write("\x01"); // TCP interface.
        $stream->writeString(''); // OS user.
        $stream->writeString(''); // Hostname.
        $stream->writeString('ClickHouse PHP simpod/clickhouse-client');
        $stream->writeVarUInt(1);
        $stream->writeVarUInt(0);
        $stream->writeVarUInt(self::Revision);
        $stream->writeString(''); // Quota key.
        $stream->writeVarUInt(0); // Distributed depth.
        $stream->writeVarUInt(0); // Patch version.
        $stream->write("\x00"); // No OpenTelemetry trace context.
        $stream->writeVarUInt(0); // Collaborate with initiator.
        $stream->writeVarUInt(0); // Participating replicas.
        $stream->writeVarUInt(0); // Replica number.
    }

    private function readException(BinaryStream $stream, int $depth = 0): NativeServerError
    {
        if ($depth >= 16) {
            throw new RuntimeException('Native exception nesting exceeds the limit.');
        }

        $unpacked = unpack('Vcode', $stream->read(4));
        assert($unpacked !== false && is_int($unpacked['code']));
        $code       = $unpacked['code'];
        $code       = $code > 2147483647 ? $code - 4294967296 : $code;
        $name       = $stream->readString();
        $message    = $stream->readString();
        $stackTrace = $stream->readString();
        $hasNested  = ord($stream->read(1));
        if ($hasNested !== 0 && $hasNested !== 1) {
            throw new RuntimeException('Invalid native exception nesting marker.');
        }

        $previous = $hasNested === 1 ? $this->readException($stream, $depth + 1) : null;

        return new NativeServerError($code, $name, $message, $stackTrace, $previous);
    }
}
