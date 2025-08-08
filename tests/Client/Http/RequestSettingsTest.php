<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Client\Http\RequestSettings;
use SimPod\ClickHouseClient\Settings\ArraySettingsProvider;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(RequestSettings::class)]
final class RequestSettingsTest extends TestCaseBase
{
    public function testMergeSettings(): void
    {
        $requestSettings = new RequestSettings(
            new ArraySettingsProvider(['database' => 'foo', 'a' => 1]),
            new ArraySettingsProvider(['database' => 'bar', 'b' => 2]),
        );

        self::assertSame('bar', $requestSettings->settings['database']);
        self::assertSame(1, $requestSettings->settings['a']);
        self::assertSame(2, $requestSettings->settings['b']);
    }
}
