<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Settings;

final readonly class EmptySettingsProvider implements SettingsProvider
{
    public function get(): array
    {
        return [];
    }
}
