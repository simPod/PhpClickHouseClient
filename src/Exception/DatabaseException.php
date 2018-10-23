<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Exception;

use Exception;

class DatabaseException extends Exception implements ClickHouseException
{
}
