<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use InvalidArgumentException;

use function gettype;
use function is_object;
use function Safe\sprintf;

final class UnsupportedValueType extends InvalidArgumentException implements ClickHouseClientException
{
    public static function value(mixed $value) : self
    {
        return new self(
            sprintf(
                'Value of type "%s" is not supported as a parameter',
                is_object($value) ? $value::class : gettype($value)
            )
        );
    }
}
