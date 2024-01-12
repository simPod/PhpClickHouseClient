<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use InvalidArgumentException;
use SimPod\ClickHouseClient\Sql\Type;

final class UnsupportedParamType extends InvalidArgumentException implements ClickHouseClientException
{
    public static function fromType(Type $type): self
    {
        return new self($type->name);
    }

    public static function fromString(string $type): self
    {
        return new self($type);
    }
}
