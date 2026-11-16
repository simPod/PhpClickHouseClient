<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use Amp\ByteStream\Payload;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\StreamException;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Pipeline\Queue;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Client\Http\CancellationStream;
use SimPod\ClickHouseClient\Tests\Client\Http\Fixture\TrackedCancellation;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

use function Amp\async;

#[CoversClass(CancellationStream::class)]
final class CancellationStreamTest extends TestCaseBase
{
    /** @param 'read'|'buffer' $method */
    #[DataProvider('provideBufferedPayloadHonoursOperationCancellation')]
    public function testBufferedPayloadHonoursOperationCancellation(string $method): void
    {
        $cancellation = new DeferredCancellation();
        $payload      = new Payload(new CancellationStream(
            new Payload("1\n"),
            $cancellation->getCancellation(),
        ));

        // Do not yield: cancellation must also be checked before queued callbacks have run.
        $cancellation->cancel();

        $this->expectException(CancelledException::class);

        try {
            if ($method === 'read') {
                $payload->read();
            } else {
                $payload->buffer();
            }
        } finally {
            self::assertTrue($payload->isClosed());
        }
    }

    /** @phpstan-return Generator<string, array{'read'|'buffer'}> */
    public static function provideBufferedPayloadHonoursOperationCancellation(): Generator
    {
        yield 'read buffered data' => ['read'];
        yield 'buffer buffered data' => ['buffer'];
    }

    #[DataProvider('provideReadCancellation')]
    public function testReadCancellation(bool $cancelOperation): void
    {
        /** @var Queue<string> $queue */
        $queue        = new Queue();
        $source       = new ReadableIterableStream($queue->iterate());
        $operation    = new DeferredCancellation();
        $read         = new DeferredCancellation();
        $payload      = new Payload(new CancellationStream($source, $operation->getCancellation()));
        /** @var DeferredFuture<null> $started */
        $started = new DeferredFuture();
        $future  = async(static function () use ($started, $payload, $read): string|null {
            $started->complete();

            return $payload->read($read->getCancellation());
        });

        $started->getFuture()->await();
        ($cancelOperation ? $operation : $read)->cancel();

        try {
            $future->await();
            self::fail('The pending read was not cancelled.');
        } catch (CancelledException) {
            self::assertSame($cancelOperation, $source->isClosed());
        }

        if (! $cancelOperation) {
            // A read-only token does not replace or cancel the operation token, or close a reusable source.
            self::assertFalse($operation->isCancelled());
            $consumed = $queue->pushAsync("next\n");
            self::assertSame("next\n", $payload->read());
            $consumed->await();
        }

        $payload->close();
    }

    /** @phpstan-return Generator<string, array{bool}> */
    public static function provideReadCancellation(): Generator
    {
        yield 'operation token remains active with an explicit read token' => [true];
        yield 'explicit read token can cancel only its read' => [false];
    }

    public function testCancellationClosesAnIdleSource(): void
    {
        $source       = new ReadableIterableStream(["1\n"]);
        $cancellation = new TrackedCancellation();
        $payload      = new Payload(new CancellationStream($source, $cancellation));
        $cancellation->cancel();

        // Run queued cancellation callbacks without reading the payload.
        async(static fn () => null)->await();

        self::assertTrue($source->isClosed());
        self::assertSame(0, $cancellation->countSubscriptions());

        $this->expectException(CancelledException::class);
        $payload->read();
    }

    /** @param 'close'|'eof'|'failure' $ending */
    #[DataProvider('provideSubscriptionCleanup')]
    public function testSubscriptionCleanup(string $ending): void
    {
        $source = new ReadableIterableStream((static function () use ($ending): Generator {
            yield "1\n";

            if ($ending === 'failure') {
                throw new StreamException('Response body failed.');
            }
        })());

        $cancellation = new TrackedCancellation();
        $payload      = new Payload(new CancellationStream($source, $cancellation));
        self::assertSame("1\n", $payload->read());
        self::assertGreaterThan(0, $cancellation->countSubscriptions());

        if ($ending === 'close') {
            $payload->close();
            $payload->close();
        } elseif ($ending === 'eof') {
            self::assertNull($payload->read());
        } else {
            try {
                $payload->read();
                self::fail('The response body failure was not propagated.');
            } catch (StreamException $exception) {
                self::assertSame('Response body failed.', $exception->getMessage());
            }
        }

        self::assertTrue($source->isClosed());
        self::assertSame(0, $cancellation->countSubscriptions());
        $cancellation->cancel();
        self::assertNull($payload->read());
    }

    /** @phpstan-return Generator<string, array{'close'|'eof'|'failure'}> */
    public static function provideSubscriptionCleanup(): Generator
    {
        yield 'explicit close is idempotent' => ['close'];
        yield 'EOF without an onClose notification' => ['eof'];
        yield 'terminal body failure' => ['failure'];
    }
}
