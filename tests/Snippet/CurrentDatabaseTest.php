<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\CurrentDatabase;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\CurrentDatabase */
final class CurrentDatabaseTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        self::assertSame(
            $this->currentDbName,
            CurrentDatabase::run($this->client)
        );
    }
}
