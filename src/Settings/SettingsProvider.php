<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Settings;

/** @phpstan-type Settings array<string, float|int|string> */
interface SettingsProvider
{
    /** @phpstan-return Settings */
    public function get(): array;
}
