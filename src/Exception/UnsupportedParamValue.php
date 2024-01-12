<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use InvalidArgumentException;

use function get_debug_type;
use function is_object;
use function sprintf;
use function var_export;

final class UnsupportedParamValue extends InvalidArgumentException implements ClickHouseClientException
{
    public static function type(mixed $value): self
    {
        return new self(
            sprintf(
                'Value of type "%s" is not supported as a parameter',
                is_object($value) ? $value::class : get_debug_type($value),
            ),
        );
    }

    public static function value(mixed $value): self
    {
        return new self(
            sprintf(
                'Value "%s" is not supported as a parameter',
                var_export($value, true),
            ),
        );
    }
}
