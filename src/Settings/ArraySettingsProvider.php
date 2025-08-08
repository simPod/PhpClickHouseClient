<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Settings;

/** @phpstan-import-type Settings from SettingsProvider */
final readonly class ArraySettingsProvider implements SettingsProvider
{
    /** @phpstan-param Settings $settings */
    public function __construct(private array $settings = [])
    {
    }

    public function get(): array
    {
        return $this->settings;
    }
}
