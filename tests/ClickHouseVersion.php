<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use RuntimeException;

use function assert;
use function explode;
use function is_string;
use function Safe\sprintf;
use function str_pad;
use function strpos;

use const STR_PAD_LEFT;

final class ClickHouseVersion
{
    private const ENV_NAME = 'CLICKHOUSE_VERSION';

    public static function get() : int
    {
        $versionString = $_ENV[self::ENV_NAME];
        assert(is_string($versionString));

        if (strpos($versionString, '.') === false) {
            throw new RuntimeException(sprintf('Specify also a ClickHouse minor version. "%s" given.', $versionString));
        }

        [$major, $minor] = explode('.', $versionString, 2);

        return (int) (str_pad($major, 2, '0', STR_PAD_LEFT) . str_pad($minor, 2, '0', STR_PAD_LEFT));
    }
}
