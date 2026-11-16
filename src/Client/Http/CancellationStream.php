<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredFuture;
use Closure;
use IteratorAggregate;
use Throwable;
use WeakReference;

/**
 * @internal
 *
 * @implements IteratorAggregate<int, string>
 */
final class CancellationStream implements ReadableStream, IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    private string|null $subscription = null;

    private CancelledException|null $exception = null;

    private ReadableStream|null $source;

    /** @var DeferredFuture<null> */
    private readonly DeferredFuture $onClose;

    public function __construct(
        ReadableStream $source,
        private readonly Cancellation $cancellation,
    ) {
        $this->source  = $source;
        $this->onClose = new DeferredFuture();
        $reference     = WeakReference::create($this);

        $subscription = $cancellation->subscribe(
            static function (CancelledException $exception) use ($reference): void {
                $reference->get()?->cancel($exception);
            },
        );

        // A Cancellation implementation may invoke its callback before subscribe() returns.
        if ($this->source === null) {
            $cancellation->unsubscribe($subscription);
        } else {
            $this->subscription = $subscription;
            $this->source->onClose(static function () use ($reference): void {
                $reference->get()?->close();
            });
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @throws CancelledException
     * @throws StreamException
     */
    public function read(Cancellation|null $cancellation = null): string|null
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $source = $this->source;

        if ($source === null) {
            return null;
        }

        $readCancellation = $cancellation === null
            ? $this->cancellation
            : new CompositeCancellation($this->cancellation, $cancellation);

        try {
            // Buffered sources need not inspect cancellation, and older composites update asynchronously.
            $this->throwIfCancelled();
            $cancellation?->throwIfRequested();
            $chunk = $source->read($readCancellation);
            $this->throwIfCancelled();
        } catch (Throwable $exception) {
            // Closing a pending source read can produce a stream error or EOF instead of cancellation.
            $this->throwIfCancelled();

            if (! $exception instanceof CancelledException || ! $cancellation?->isRequested()) {
                $this->close();
            }

            throw $exception;
        }

        if ($chunk === null) {
            $this->close();
        }

        return $chunk;
    }

    public function isReadable(): bool
    {
        return $this->source?->isReadable() ?? false;
    }

    public function close(): void
    {
        $source = $this->source;

        if ($source === null) {
            return;
        }

        $this->source = null;

        if ($this->subscription !== null) {
            $this->cancellation->unsubscribe($this->subscription);
            $this->subscription = null;
        }

        try {
            $source->close();
        } finally {
            $this->onClose->complete();
        }
    }

    public function isClosed(): bool
    {
        return ! $this->isReadable();
    }

    public function onClose(Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }

    private function cancel(CancelledException $exception): void
    {
        if ($this->source === null) {
            return;
        }

        $this->exception = $exception;
        $this->close();
    }

    /** @throws CancelledException */
    private function throwIfCancelled(): void
    {
        try {
            $this->cancellation->throwIfRequested();
        } catch (CancelledException $exception) {
            $this->cancel($exception);

            throw $exception;
        }
    }
}
