<?php

declare(strict_types=1);

use SimPod\ClickHouseClient\Tests\Client\Native\ScriptedNativeServer;

require __DIR__ . '/ScriptedNativeServer.php';

try {
    $arguments = $_SERVER['argv'] ?? [];
    $scenario  = is_array($arguments) ? ($arguments[1] ?? null) : null;
    if (! is_string($scenario)) {
        throw new RuntimeException('Missing native test scenario.');
    }

    new ScriptedNativeServer()->run($scenario);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
