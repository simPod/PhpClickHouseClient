# PHP ClickHouse Client

[![Build Status](https://github.com/simPod/PhpClickHouseClient/workflows/CI/badge.svg?branch=master)](https://github.com/simPod/PhpClickHouseClient/actions)
[![Code Coverage][Coverage image]][CodeCov Master]
[![Downloads](https://poser.pugx.org/simpod/clickhouse-client/d/total.svg)](https://packagist.org/packages/simpod/clickhouse-client)
[![Infection MSI](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FsimPod%2FPhpClickHouseClient%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/simPod/PhpClickHouseClient/master)

## Motivation

The library is trying not to hide any ClickHouse HTTP interface specific details. 
That said everything is as much transparent as possible and so object-oriented API is provided without inventing own abstractions.  
Naming used here is the same as in ClickHouse docs. 

- Works with any HTTP Client implementation ([PSR-18 compliant](https://www.php-fig.org/psr/psr-18/))
- All [ClickHouse Formats](https://clickhouse.yandex/docs/en/interfaces/formats/) support
- Logging ([PSR-3 compliant](https://www.php-fig.org/psr/psr-3/))
- SQL Factory for [parameters "binding"](#parameters-binding)
- [Native query parameters](#native-query-parameters) support

## Contents

- [Setup](#setup)
  - [Logging](#logging)
  - [PSR Factories who?](#psr-factories-who)
- [Sync API](#sync-api)
  - [Select](#select)
  - [Select With Params](#select-with-params)
  - [Insert](#insert)
- [Async API](#async-api)
  - [Select](#select-1)
- [Native Query Parameters](#native-query-parameters)
- [Snippets](#snippets)

## Setup

```sh
composer require simpod/clickhouse-client  
```

1. Read about ClickHouse [Http Interface](https://clickhouse.com/docs/en/interfaces/http/). _It's short and useful for concept understanding._
2. Create a new instance of ClickHouse client and pass PSR factories.
   1. Symfony HttpClient is recommended (performance, less bugs, maintenance)
   2. The plot twist is there's no endpoint/credentials etc. config in this library, provide it via client
3. See tests

```php
<?php

use Http\Client\Curl\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;

$psr17Factory = new Psr17Factory;

$clickHouseClient = new PsrClickHouseClient(
    new Client(),
    new RequestFactory(
        new ParamValueConverterRegistry(),
        $psr17Factory,
        $psr17Factory
    ),
    new LoggerChain(),
    [],
);
```

### Symfony HttpClient Example

Configure HTTP Client

As said in ClickHouse HTTP Interface spec, we use headers to auth and e.g. set default database via query.

```yaml
framework:
    http_client:
        scoped_clients:
            click_house.client:
                base_uri: '%clickhouse.endpoint%'
                headers:
                    'X-ClickHouse-User': '%clickhouse.username%'
                    'X-ClickHouse-Key': '%clickhouse.password%'
                query:
                    database: '%clickhouse.database%'
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

### Select With Params

`ClickHouseClient::selectWithParams()`

Same as `ClickHouseClient::select()` except it also allows [parameter binding](#parameters-binding).

```php
<?php

use SimPod\ClickHouseClient\Client\ClickHouseClient;
use SimPod\ClickHouseClient\Format\JsonEachRow;
use SimPod\ClickHouseClient\Output;

/** @var ClickHouseClient $client */
/** @var Output\JsonEachRow $output */
$output = $client->selectWithParams(
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

If `$columnNames` is provided and is key->value array column names are generated based on it and values are passed as parameters:

`$client->insert( 'table', [[1,2]], ['a' => 'Int8, 'b' => 'String'] );` generates `INSERT INTO table (a,b) VALUES ({p1:Int8},{p2:String})` and values are passed along the query.

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
- DateTimeInterface
- [Expression](#expression)
- objects implementing `__toString()`

## Native Query Parameters

> [!TIP]
> [Official docs](https://clickhouse.com/docs/en/interfaces/http#cli-queries-with-parameters)

```php
<?php

use SimPod\ClickHouseClient\Client\PsrClickHouseClient;

$client = new PsrClickHouseClient(...);

$output = $client->selectWithParams(
    'SELECT {p1:String}',
    ['param' => 'value']
);
```

All types are supported (except `AggregateFunction`, `SimpleAggregateFunction` and `Nothing` by design).
You can also pass `DateTimeInterface` into `Date*` types or native array into `Array`, `Tuple`, `Native` and `Geo` types

### Custom Query Parameter Value Conversion

Query parameters passed to `selectWithParams()` are converted into an HTTP-API-compatible format. To overwrite an existing value converter or
provide a converter for a type that the library does not (yet) support, pass these to the
`SimPod\ClickHouseClient\Param\ParamValueConverterRegistry` constructor:

```php
<?php

use SimPod\ClickHouseClient\Client\Http\RequestFactory;
use SimPod\ClickHouseClient\Client\PsrClickHouseClient;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;

$paramValueConverterRegistry = new ParamValueConverterRegistry([
    'datetime' => static fn (mixed $v) => $v instanceof DateTimeInterface ? $v->format('c') : throw UnsupportedParamValue::type($value)
]);

$client = new PsrClickHouseClient(..., new RequestFactory($paramValueConverterRegistry, ...));
```

Be aware that the library can not ensure that passed values have a certain type. They are passed as-is and closures must accept `mixed` values.

Throw an exception of type `UnsupportedParamValue` if your converter does not support the passed value type.

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
- DatabaseSize
- Parts
- ShowCreateTable
- ShowDatabases
- TableSizes
- Version

[Coverage image]: https://codecov.io/gh/simPod/PhpClickHouseClient/branch/master/graph/badge.svg
[CodeCov Master]: https://codecov.io/gh/simPod/PhpClickHouseClient/branch/master
