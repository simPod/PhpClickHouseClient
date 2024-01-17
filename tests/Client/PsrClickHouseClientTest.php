<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Tests\TestCaseBase;
use SimPod\ClickHouseClient\Tests\WithClient;

#[CoversClass(PsrClickHouseClient::class)]
final class PsrClickHouseClientTest extends TestCaseBase
{
    use WithClient;

    public function testExecuteQueryWithParams(): void
    {
        self::$client->executeQueryWithParams(
            'SHOW DATABASES ILIKE :database',
            ['database' => self::$currentDbName],
        );

        $this->addToAssertionCount(1);
    }
}
