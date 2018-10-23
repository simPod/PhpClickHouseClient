<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use InvalidArgumentException;
use function get_class;
use function gettype;
use function is_object;
use function Safe\sprintf;

final class UnsupportedValueType extends InvalidArgumentException implements ClickHouseClientException
{
    /** @param mixed $value */
    public static function value($value) : self
    {
        return new self(
            sprintf(
                'Value of type "%s" is not supported as a parameter',
                is_object($value) ? get_class($value) : gettype($value)
            )
        );
    }
}
