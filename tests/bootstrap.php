<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Tests;

use function date_default_timezone_set;
use function error_reporting;
use const E_ALL;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Prague');
error_reporting(E_ALL);
