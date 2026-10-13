# Native TCP

`Client\NativeClickHouseClient` uses ClickHouse's native TCP protocol alongside the
existing HTTP clients. It requires 64-bit PHP and a server supporting protocol revision
54459 or newer. The existing CI server matrix starts at ClickHouse 24.3.

## Connection

Pass `Client\Native\ConnectionConfig` to the client. Connections open lazily and remain
open after successful operations. `close()` releases the socket; the next operation
opens a new connection. Each client owns one connection and runs one operation at a time.

The default port is 9000, or 9440 with `tls: true`. TLS always checks the server
certificate and hostname. `caFile` selects a private CA, and `serverName` selects the
verified TLS name when connecting through a tunnel. There is no option to disable
certificate verification. The PHP OpenSSL extension is required for TLS.

`connectTimeout` defaults to 5 seconds. `readTimeout` defaults to 30 seconds and is an
I/O timeout, not a whole-query deadline. Without TLS, credentials are sent in plaintext.

## Operations and results

- `select($sql, $settings)` returns a buffered `Native\Result`.
- `selectWithParams($sql, $params, $settings)` supports existing `:name` SQL substitution
  and server-side `{name:Type}` parameters. Native parameters are serialized as String
  Field dumps, not HTTP `param_*` values.
- `executeQuery()` and `executeQueryWithParams()` execute SQL and discard result rows.
- `insert($table, $values, $columns, $settings)` sends binary blocks, not SQL value literals.

Per-operation settings override constructor defaults. Unknown server settings are
important and cause a server error instead of being silently ignored. The
`output_format_native_encode_types_in_binary_format` setting is not supported.

`Result::$columns` contains ordered `Column` objects with `name` and `type` strings.
`Result::$rows` contains positional lists in that order. This retains duplicate column
names without overwriting data. `totals` and `extremes` contain their separate rows when
requested by the query. Progress, logs, and profile packets are consumed internally.

Results are fully received before returning, including any late server exception.
Zero-row schema and boundary blocks do not end a query; only `EndOfStream` does.
There is no `Format` argument: TCP always carries native blocks, even if SQL includes
a `FORMAT` clause. Use HTTP when server-produced JSON, CSV, or other formats are needed.

## Binary value mappings

Supported types are `Int8/16/32/64`, `UInt8/16/32/64`, `Float32/64`, `Bool`, `String`,
`FixedString(N)`, `Date`, `Date32`, `DateTime`, `DateTime64`, `Nullable(T)`, `Array(T)`, and
unnamed `Tuple(T, ...)`.
`Enum8` and `Enum16` use signed numeric indexes. Inserts require an index declared
by the enum type; enum query parameters instead use label strings.

Integers map to PHP integers. `UInt64` values above `PHP_INT_MAX` map to exact decimal
strings, never floats. Insert values must fit the declared column type.
Floats map to PHP floats; booleans map to PHP booleans. Strings retain arbitrary bytes,
including NUL; they need not be UTF-8. Fixed strings retain padding bytes.

Dates use integer day counts since 1970-01-01. `DateTime` uses integer Unix seconds.
`DateTime64` uses integer ticks at its declared scale. Timezone annotations remain in
column metadata; values are not converted to formatted local dates. Inserts use the
same representations. Nullable values use PHP `null`; arrays and tuples use ordered PHP lists.

Unsupported types fail explicitly. This includes wide integers, decimals, UUID/IP
types, named tuples, LowCardinality, Map, JSON/Dynamic/Variant, and aggregate states.
Type support for query parameters is independent of binary result support:
a parameter converter does not make that type readable in a result block.
Nested nullable nulls and nested date values are not supported by the existing parameter
converter registry. For those composite parameters, supply correctly serialized text
or use a custom converter. Top-level nullable nulls are supported.

## Inserts and failures

Provide positional row lists and optionally an ordered list of column names. Omit
column names to use the server's insert schema order. Associative rows and HTTP-style
name-to-type column maps are not supported. Iterable input is consumed in batches;
`insertBlockSize` defaults to 10000 and is limited to 1000000 rows per block.

Empty input is rejected. Every row must match the returned schema. The codec validates
values rather than truncating or wrapping them. Server errors use `NativeServerError`,
with the ClickHouse error code, exception name, and server stack trace. I/O, unsupported
type, and malformed protocol errors use `RuntimeException`.

Any failure closes the connection. A later operation reconnects, but the failed operation
is never retried automatically. Previously accepted insert blocks may remain stored;
there is no client rollback guarantee. Apply a deliberate deduplication policy before
retrying an insert whose outcome is uncertain.

Received strings and individual binary reads are limited to 64 MiB, blocks to 1024
columns and 1000000 rows, and nested encodings to a bounded depth. Column names are
limited to 64 KiB and type declarations to 4096 bytes. A shared 64 MiB allocation budget
accounts conservatively for decoded containers, cells, and payload bytes within a block.
Buffered results have the same cumulative budget across data, totals, and extremes.
This is an allocation estimate, not a limit on PHP process memory. Use HTTP streaming
for larger unbounded reads until native result streaming is available.

## Initial limits

No compression, async TCP, native result streaming, external tables, pooling, cancellation,
or arbitrary formatted payload insertion is included. Do not share one client across
concurrent operations. No automatic fallback to HTTP is performed.

The [architecture decision](adr/0001-native-tcp-client.md) explains the separate interfaces
and protocol revision choice.

## Integration configuration

Native integration tests use `CLICKHOUSE_TCP_HOST` and `CLICKHOUSE_TCP_PORT`, defaulting
to `127.0.0.1:9000`. Set `CLICKHOUSE_TCP_TLS=1` for TLS; `CLICKHOUSE_TCP_CA_FILE` and
`CLICKHOUSE_TCP_SERVER_NAME` select the CA file and verified certificate name.
Database and credentials use the existing `CLICKHOUSE_DATABASE`, `CLICKHOUSE_USER`,
and `CLICKHOUSE_PASSWORD` variables. Insert tests use connection-local temporary tables.
