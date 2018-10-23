# PHP ClickHouse Client

[![Build Status](https://github.com/simPod/PhpClickHouseClient/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/simPod/PhpClickHouseClient/actions)
[![Coverage Status](https://coveralls.io/repos/github/simPod/PhpClickHouseClient/badge.svg?branch=master)](https://coveralls.io/github/simPod/PhpClickHouseClient?branch=master)
[![Downloads](https://poser.pugx.org/simpod/clickhouse-client/d/total.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![Packagist](https://poser.pugx.org/simpod/clickhouse-client/v/stable.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![Licence](https://poser.pugx.org/simpod/clickhouse-client/license.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![GitHub Issues](https://img.shields.io/github/issues/simPod/PhpClickHouseClient.svg?style=flat-square)](https://github.com/simPod/PhpClickHouseClient/issues)
[![Psalm Coverage](https://shepherd.dev/github/simPod/PhpClickHouseClient/coverage.svg)](https://shepherd.dev/github/simPod/PhpClickHouseClient)

## Motivation

The library is trying not to hide any ClickHouse HTTP interface specific details. 
That said everything is as much transparent as possible and so object-oriented API is provided without inventing own abstractions.  
Naming used here is the same as in ClickHouse docs. 

- Works with any HTTP Client implementation ([PSR-18 compliant](https://www.php-fig.org/psr/psr-18/))
- All [ClickHouse Formats](https://clickhouse.yandex/docs/en/interfaces/formats/) support
- Logging ([PSR-3 compliant](https://www.php-fig.org/psr/psr-3/))
- SQL Factory for [parameters "binding"](#parameters-binding)
- Dependency only on PSR interfaces, Guzzle A+ Promises for async requests and Safe

## Contents

- [Setup](#setup)
  - [Time Zones](#time-zones)
  - [PSR Factories who?](#psr-factories-who)
- [Sync API](#sync-api)
  - [Select](#select)
  - [Select With Parameters](#select-with-parameters)
  - [Insert](#insert)
- [Async API](#async-api)
  - [Select](#select-1)
- [Parameters "binding"](#parameters-binding)
- [Snippets](#snippets)

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
use SimPod\ClickHouseClient\Client\Http\RequestFactory;

$clickHouseClient = new PsrClickHouseClient(
    new Client(),
    new RequestFactory(
        new Psr17Factory,
        new Psr17Factory,
        new Psr17Factory
    ),
    new NullLogger(),
    'https://localhost:8123',
    [
        'database' => 'dbname',
        'user' => 'username',
        'password' => 'secret',
    ],
    new DateTimeZone('UTC')
);
```

### Time Zones

ClickHouse does not have date times with timezones. 
Therefore you need to normalize DateTimes' timezones passed as parameters to ensure proper input format.

Following would be inserted as `2020-01-31 01:00:00` into ClickHouse. 

```php
new DateTimeImmutable('2020-01-31 01:00:00', new DateTimeZone('Europe/Prague'));
```

If your server uses `UTC`, the value is incorrect for you actually need to insert `2020-01-31 00:00:00`.

Time zone normalization is enabled by passing `DateTimeZone` into `PsrClickHouseClient` constructor.

```php
new PsrClickHouseClient(..., new DateTimeZone('UTC'));
```

### PSR Factories who?

_The library does not implement it's own HTTP. 
That has already been done via [PSR-7, PSR-17 and PSR-18](https://www.php-fig.org/psr/). 
This library respects it and allows you to plug your own implementation (eg. HTTPPlug or Guzzle)._

_Recommended are `composer require nyholm/psr7` for PSR-17 and `composer require php-http/curl-client` for Curl PSR-18 implementation (used in example above)._

## Sync API

### Select

`ClickHouseClient::select()`

Intended for `SELECT` and `SHOW` queries. 
Appends `FORMAT` to the query and returns response in selected output format:

```php
<?php

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Output;

/** @var ClickHouseClient $client */
/** @var Output\JsonEachRow $output */
$output = $client->select(
    'SELECT * FROM table',
    new JsonEachRow(),
    ['force_primary_key' => 1]
);
```

### Select With Parameters

`ClickHouseClient::selectWithParameters()`

Same as `ClickHouseClient::select()` except it also allows [parameter binding](#parameters-binding).

```php
<?php

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Output;

/** @var ClickHouseClient $client */
/** @var Output\JsonEachRow $output */
$output = $client->selectWithParameters(
    'SELECT * FROM :table',
    ['table' => 'table_name'],
    new JsonEachRow(),
    ['force_primary_key' => 1]
);
```

### Insert

`ClickHouseClient::insert()`

```php
<?php

use SimPod\ClickHouseClient\Client\ClickHouseClient;

/** @var ClickHouseClient $client */
$client->insert('table', $data, $columnNames);
```

If `$columnNames` is provided column names are generated based on it:

`$client->insert( 'table', [[1,2]], ['a', 'b'] );` generates `INSERT INTO table (a,b) VALUES (1,2)`.

If `$columnNames` is omitted column names are read from `$data`:
 
`$client->insert( 'table', [['a' => 1,'b' => 2]]);` generates `INSERT INTO table (a,b) VALUES (1,2)`.

Column names are read only from the first item:

`$client->insert( 'table', [['a' => 1,'b' => 2], ['c' => 3,'d' => 4]]);` generates `INSERT INTO table (a,b) VALUES (1,2),(3,4)`.

If not provided they're not passed either:

`$client->insert( 'table', [[1,2]]);` generates `INSERT INTO table VALUES (1,2)`.

## Async API

### Select

## Parameters "binding"

```php
<?php

use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

$sqlFactory = new SqlFactory(new ValueFormatter());

$sql = $sqlFactory->createWithParameters(
    'SELECT :param',
    ['param' => 'value']
);
```
This produces `SELECT 'value'` and it can be passed to `ClickHouseClient::select()`.

Supported types are:
- scalars
- DateTimeImmutable (`\DateTime` is not supported because `ValueFormatter` might modify its timezone so it's not considered safe)
- [Expression](#expression)
- objects implementing `__toString()`

### Expression

To represent complex expressions there's `SimPod\ClickHouseClient\Sql\Expression` class. When passed to `SqlFactory` its value gets evaluated.

To pass eg. `UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')` to SQL:

```php
<?php

use SimPod\ClickHouseClient\Sql\Expression;

Expression::new("UUIDStringToNum('6d38d288-5b13-4714-b6e4-faa59ffd49d8')");
```

```php
<?php

use SimPod\ClickHouseClient\Sql\ExpressionFactory;
use SimPod\ClickHouseClient\Sql\ValueFormatter;

$expressionFactory = new ExpressionFactory(new ValueFormatter());

$expression = $expressionFactory->templateAndValues(
    'UUIDStringToNum(%s)',
    '6d38d288-5b13-4714-b6e4-faa59ffd49d8'
);
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

### List

- CurrentDatabase
- ShowCreateTable
- ShowDatabases

