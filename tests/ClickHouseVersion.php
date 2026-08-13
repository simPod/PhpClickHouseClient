<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use RuntimeException;

use function explode;
use function getenv;
use function sprintf;
use function str_pad;
use function strpos;

use const STR_PAD_LEFT;

final readonly class ClickHouseVersion
{
    private const string EnvName = 'CLICKHOUSE_VERSION';

    private const int VersionJsonQuotes64BitIntegersDefaultOff = 2508;

    /** @throws RuntimeException */
    public static function get(): int
    {
        $versionString = getenv(self::EnvName);
        if ($versionString === false) {
            $versionString = '23.12';
        }

        if (strpos($versionString, '.') === false) {
            throw new RuntimeException(sprintf('Specify also a ClickHouse minor version. "%s" given.', $versionString));
        }

        [$major, $minor] = explode('.', $versionString, 2);

        return (int) (str_pad($major, 2, '0', STR_PAD_LEFT) . str_pad($minor, 2, '0', STR_PAD_LEFT));
    }

    public static function quotes64BitIntegersInJson(): bool
    {
        return self::get() < self::VersionJsonQuotes64BitIntegersDefaultOff;
    }
}
