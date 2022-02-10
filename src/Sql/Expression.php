<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

final class Expression
{
    public string $innerExpression;

    private function __construct(string $expression)
    {
        $this->innerExpression = $expression;
    }

    public static function new(string $expression): self
    {
        return new self($expression);
    }

    public function __toString(): string
    {
        return $this->innerExpression;
    }

    public function toString(): string
    {
        return $this->innerExpression;
    }
}
