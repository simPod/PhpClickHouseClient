<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\DatabaseSize;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\DatabaseSize */
final class DatabaseSizeTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        self::assertSame(0, DatabaseSize::run($this->client));
    }
}
