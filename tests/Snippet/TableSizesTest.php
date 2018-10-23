<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\TableSizes;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\TableSizes */
final class TableSizesTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        self::assertSame([], TableSizes::run($this->client));
    }
}
