<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\Parts;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\Parts */
final class PartsTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        self::assertSame([], Parts::run($this->client, 'system.query_log'));
    }
}
