<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Schema;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Schema\Table;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Table::class)]
final class TableTest extends TestCaseBase
{
    public function testConstruct(): void
    {
        $table = new Table('t1', 'db');
        self::assertSame('t1', $table->name);
        self::assertSame('db', $table->database);
    }

    #[DataProvider('providerFullName')]
    public function testFullName(string $expectedFullName, Table $table): void
    {
        self::assertSame($expectedFullName, $table->fullName());
    }

    /** @return Generator<string, array{string, Table}> */
    public static function providerFullName(): Generator
    {
        yield 'no database' => [
            '`t1`',
            new Table('t1'),
        ];

        yield 'with database' => [
            '`db`.`t1`',
            new Table('t1', 'db'),
        ];

        yield 'escaped' => [
            '`db`.`t1`',
            new Table('`t1`', '`db`'),
        ];
    }
}
