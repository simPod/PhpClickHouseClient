<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Sql\Type;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(Type::class)]
final class TypeTest extends TestCaseBase
{
    #[DataProvider('providerFromString')]
    public function testFromString(string $input, string $expectedName, string $expectedParams): void
    {
        $type = Type::fromString($input);

        self::assertSame($expectedName, $type->name);
        self::assertSame($expectedParams, $type->params);
    }

    /** @return Generator<int, array{string, string, string}> */
    public static function providerFromString(): Generator
    {
        yield ['Int32', 'Int32', ''];
        yield ['Tuple(String, Int)', 'Tuple', 'String, Int'];
    }
}
