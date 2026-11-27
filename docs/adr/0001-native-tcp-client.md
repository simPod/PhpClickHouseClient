# Native TCP alongside HTTP

## Status

Accepted

## Context

The client currently uses ClickHouse HTTP. Its public interface requires a SQL output
format and parses formatted response bytes. Native TCP instead carries typed column
blocks and has a stateful query and insert exchange. A TCP client must handle protocol
revision gates, binary column encodings, and complete packet sequences.

Native TCP support must not change existing HTTP callers or require converting native
blocks into JSON merely to reuse HTTP output parsers.

## Decision

Add `NativeClickHouseClient` with a separate result interface. Keep the HTTP interfaces
and implementations unchanged. Reuse existing SQL preparation, parameter discovery,
parameter value converters, server settings, table identifiers, and SQL logging where
their contracts apply.

The initial implementation uses synchronous PHP streams on 64-bit PHP and advertises
protocol revision 54459. Newer servers negotiate that revision; older revisions are
rejected. Implement only the fields and column encodings supported at that revision.
TLS verifies the peer certificate and name. Plain TCP remains available for trusted
networks. Add no native extensions or protocol dependencies.

Return buffered positional rows and column metadata. Send inserts in bounded batches,
using the schema supplied by the server. Drain responses through `EndOfStream` before
connection reuse. Close on any protocol, I/O, server, or caller-supplied insert failure.
Do not automatically retry a query with an uncertain outcome.

Compression, async TCP, streamed query results, arbitrary formatted payloads, external
tables, and unsupported binary types are outside the initial implementation. Report
unsupported types explicitly instead of changing values or silently selecting HTTP.

## Consequences

HTTP behavior remains compatible. Native TCP callers learn a small separate interface
and receive typed values without JSON conversion. They cannot use HTTP format parsers
or HTTP-only snippets directly with the native client.

Buffered results consume memory in proportion to the result size and have a cumulative
allocation budget. Decoders share a per-block budget, including nested values, before
allocation. One connection runs one query at a time. Applications need separate clients
for independent operations.
Insert errors can leave previously accepted blocks on the server, so callers must not
assume rollback or retry inserts without their own deduplication policy.

Supporting more types and newer revisions requires explicit wire codecs and independent
protocol fixtures plus server integration coverage. Native TCP performance must be
measured rather than assumed.

See [native TCP capabilities](../native-tcp.md), the
[native protocol specification](https://clickhouse.com/docs/resources/develop-contribute/native-protocol/protocol),
and the [native format specification](https://clickhouse.com/docs/resources/develop-contribute/native-protocol/format).
