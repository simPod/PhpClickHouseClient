<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client\Http;

use SimPod\ClickHouseClient\Client\Http\RequestOptions;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Client\Http\RequestOptions */
final class RequestOptionsTest extends TestCaseBase
{
    public function testMergeSettings(): void
    {
        $requestOptions = new RequestOptions(
            '',
            ['database' => 'foo', 'a' => 1],
            ['database' => 'bar', 'b' => 2]
        );

        self::assertSame('bar', $requestOptions->settings['database']);
        self::assertSame(1, $requestOptions->settings['a']);
        self::assertSame(2, $requestOptions->settings['b']);
    }
}
