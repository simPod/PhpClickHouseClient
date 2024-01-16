<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Snippet\Version;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(Version::class)]
final class VersionTest extends TestCaseBase
{
    use WithClient;

    public function testRun(): void
    {
        self::assertMatchesRegularExpression('~(\d+\.)+\d+~', Version::run(self::$client));
    }
}
