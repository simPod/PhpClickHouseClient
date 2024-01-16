<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\Parts;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(Parts::class)]
final class PartsTest extends TestCaseBase
{
    use WithClient;

    public function testRun(): void
    {
        self::assertSame([], Parts::run(self::$client, 'system.query_log'));
    }
}
