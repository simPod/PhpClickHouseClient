<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(EmptySettingsProvider::class)]
final class EmptySettingsProviderTest extends TestCaseBase
{
    public function testGetReturnsEmptyArray(): void
    {
        $provider = new EmptySettingsProvider();

        self::assertSame([], $provider->get());
    }
}
