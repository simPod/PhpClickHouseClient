<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http\Fixture;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Closure;

use function count;

final class TrackedCancellation implements Cancellation
{
    /** @var array<string, true> */
    private array $subscriptions = [];

    private readonly DeferredCancellation $deferred;

    private readonly Cancellation $cancellation;

    public function __construct()
    {
        $this->deferred     = new DeferredCancellation();
        $this->cancellation = $this->deferred->getCancellation();
    }

    public function subscribe(Closure $callback): string
    {
        $id                       = $this->cancellation->subscribe($callback);
        $this->subscriptions[$id] = true;

        return $id;
    }

    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
        unset($this->subscriptions[$id]);
    }

    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    /** @throws CancelledException */
    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }

    public function cancel(): void
    {
        $this->deferred->cancel();
    }

    public function countSubscriptions(): int
    {
        return count($this->subscriptions);
    }
}
