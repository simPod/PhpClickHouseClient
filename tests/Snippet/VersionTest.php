<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use SimPod\ClickHouseClient\Snippet\Version;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

/** @covers \SimPod\ClickHouseClient\Snippet\Version */
final class VersionTest extends TestCaseBase
{
    use WithClient;

    public function testRun() : void
    {
        self::assertMatchesRegularExpression('~(\d+\.)+\d+~', Version::run($this->client));
    }
}
