<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\CurrentDatabase;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(CurrentDatabase::class)]
final class CurrentDatabaseTest extends TestCaseBase
{
    use WithClient;

    public function testRun(): void
    {
        self::assertSame(
            $this->currentDbName,
            CurrentDatabase::run($this->client),
        );
    }
}
