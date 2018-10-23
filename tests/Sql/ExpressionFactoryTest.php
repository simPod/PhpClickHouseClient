<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use DateTimeZone;
use SimPod\ClickHouseClient\Sql\ExpressionFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

/** @covers \SimPod\ClickHouseClient\Sql\ExpressionFactory */
final class ExpressionFactoryTest extends TestCaseBase
{
    /**
     * @param array<mixed> $values
     *
     * @dataProvider providerTemplateAndValues
     */
    public function testTemplateAndValues(string $expectedExpressionString, string $template, array $values) : void
    {
        $expressionFactory = new ExpressionFactory(new ValueFormatter(new DateTimeZone('UTC')));

        self::assertSame(
            $expectedExpressionString,
            (string) $expressionFactory->templateAndValues($template, ...$values)
        );
    }

    /** @return iterable<int, array{string, string, array<mixed>}> */
    public function providerTemplateAndValues() : iterable
    {
        yield [
            "UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')",
            'UUIDStringToNum(%s)',
            ['6d38d288-5b13-4714-b6e4-faa59ffd49d8'],
        ];

        yield [
            'power(2,3)',
            'power(%d,%d)',
            [2, 3],
        ];
    }
}
