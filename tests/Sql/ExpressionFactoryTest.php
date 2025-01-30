<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SimPod\ClickHouseClient\Sql\ExpressionFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use SimPod\ClickHouseClient\Tests\TestCaseBase;

#[CoversClass(ExpressionFactory::class)]
final class ExpressionFactoryTest extends TestCaseBase
{
    /** @param array<mixed> $values */
    #[DataProvider('providerTemplateAndValues')]
    public function testTemplateAndValues(string $expectedExpressionString, string $template, array $values): void
    {
        $expressionFactory = new ExpressionFactory(new ValueFormatter());

        self::assertSame(
            $expectedExpressionString,
            (string) $expressionFactory->templateAndValues($template, ...$values),
        );
    }

    /** @return iterable<int, array{string, string, array<mixed>}> */
    public static function providerTemplateAndValues(): iterable
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
