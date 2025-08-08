<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(ArraySettingsProvider::class)]
final class ArraySettingsProviderTest extends TestCaseBase
{
    public function testGetWithEmptyArray(): void
    {
        $provider = new ArraySettingsProvider();

        self::assertSame([], $provider->get());
    }

    public function testGetWithSettings(): void
    {
        $settings = [
            'max_memory_usage' => 1000000000,
            'send_logs_level' => 'trace',
            'connect_timeout' => 10.0,
        ];

        $provider = new ArraySettingsProvider($settings);

        self::assertSame($settings, $provider->get());
    }
}
