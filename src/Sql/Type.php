<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Sql;

use function assert;
use function preg_match;

final readonly class Type
{
    private function __construct(public string $name, public string $params)
    {
    }

    public static function fromString(string $type): self
    {
        $result = preg_match('~([a-zA-Z\d ]+)(?:\((.+)\))?~', $type, $matches);
        assert($result === 1);

        return new self($matches[1], $matches[2] ?? '');
    }
}
