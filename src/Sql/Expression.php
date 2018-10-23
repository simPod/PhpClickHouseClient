<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function Safe\sprintf;

final class Expression
{
    /** @var string */
    public $innerExpression;

    private function __construct(string $expression)
    {
        $this->innerExpression = $expression;
    }

    public static function new(string $expression) : self
    {
        return new self($expression);
    }

    /**
     * @param mixed $value
     */
    public static function fromTemplateAndValue(string $template, $value) : self
    {
        return new self(sprintf($template, ValueFormatter::format($value)));
    }

    public function __toString() : string
    {
        return $this->innerExpression;
    }
}
