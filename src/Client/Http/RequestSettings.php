<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Http;

use SimPod\ClickHouseClient\Settings\SettingsProvider;

/** @phpstan-import-type Settings from SettingsProvider */
final readonly class RequestSettings
{
    /** @phpstan-var Settings */
    public array $settings;

    public function __construct(
        SettingsProvider $defaultSettings,
        SettingsProvider $querySettings,
    ) {
        $this->settings = $querySettings->get() + $defaultSettings->get();
    }
}
