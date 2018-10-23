# PHP ClickHouse Client

[![Build Status](https://github.com/simPod/PhpClickHouseClient/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/simPod/PhpClickHouseClient/actions)
[![Coverage Status](https://coveralls.io/repos/github/simPod/PhpClickHouseClient/badge.svg?branch=master)](https://coveralls.io/github/simPod/PhpClickHouseClient?branch=master)
[![Downloads](https://poser.pugx.org/simpod/clickhouse-client/d/total.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![Packagist](https://poser.pugx.org/simpod/clickhouse-client/v/stable.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![Licence](https://poser.pugx.org/simpod/clickhouse-client/license.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![GitHub Issues](https://img.shields.io/github/issues/simPod/PhpClickHouseClient.svg?style=flat-square)](https://github.com/simPod/PhpClickHouseClient/issues)

PHP Client that talks to ClickHouse via HTTP layer

## Motivation

The library is trying not to hide any ClickHouse HTTP interface specific details. 
That said everything is as much transparent as possible and so object-oriented API is provided without inventing own abstractions.

## Setup

```sh
composer require simpod/clickhouse-client  
```

Create a new instance of client and pass PSR factories:

```php
<?php

use Http\Client\Curl\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\NullLogger;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Http\RequestFactory;

$clickHouseClient = new PsrClickHouseClient(
    new Client(),
    new RequestFactory(
        new Psr17Factory,
        new Psr17Factory,
        new Psr17Factory
    ),
    new NullLogger(),
    'localhost:8123',
    [
        'database' => 'dbname',
        'user' => 'username',
        'password' => 'secret',
    ]
);
```

### PSR Factories who?

_The library does not implement it's own HTTP. 
That has already been done via [PSR-7, PSR-17 and PSR-18](https://www.php-fig.org/psr/). 
This library respects it and allows you to plug your own implementation (eg. HTTPPlug or Guzzle)._

_Recommended are `composer require nyholm/psr7` for PSR-17 and `composer require php-http/curl-client` for Curl PSR-18 implementation (used in example above)._

## API

TODO

## Parameters "binding"

ClickHouse does not support parameter binding so there's no point to pretend otherwise.
However, the `:param` replacement is built for comfort.

```php
<?php

use SimPod\ClickHouseClient\Sql\SqlFactory;

$sql = SqlFactory::createWithParameters(
    <<<CLICKHOUSE
SELECT :param
CLICKHOUSE,
    ['param' => 'value']
);
```
This produces `SELECT 'value'` and it can be passed to `ClickHouseClient::select()`.

Supported types are:
- scalars
- DateTimeInterface
- Expression
- objects implementing `__toString()`

### Expression

To represent complex expressions there's `SimPod\ClickHouseClient\Sql\Expression` class. When passed to `SqlFactory` its value gets evaluated.

To pass eg. `UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')` to SQL:

```php
<?php

use SimPod\ClickHouseClient\Sql\Expression;

Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')");
Expression::fromTemplateAndValue('UUIDStringToNum(%s)', '6d38d288-5b13-4714-b6e4-faa59ffd49d8');
```

## Snippets

There are handy queries like getting database size, table list, current database etc.

To prevent Client API pollution, those are extracted into Snippets.

Example to obtain current database name:
```php
<?php

use SimPod\ClickHouseClient\Snippet\CurrentDatabase;

$currentDatabaseName = CurrentDatabase::run($client);
```

