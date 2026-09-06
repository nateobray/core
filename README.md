# Obray Core

A small PHP framework with convention-based controllers, dependency injection,
HTTP and console routing, response encoders, and a MySQL model/query layer.

## Requirements and installation

- PHP 8.0 or newer. The source uses union types, `mixed`, and named arguments.
- PDO and `pdo_mysql` when using the database layer, with a MySQL database.

```sh
composer require obray/core
```

## Request handling

The request flow is `ServerRequest → Router → Factory → Invoker → Encoder`.
Applications autoload their controllers under `controllers\\`, for example
`controllers\\Status` in `src/controllers/Status.php`:

```php
<?php
namespace controllers;

use obray\core\http\requests\GETRequest;
use obray\users\Permission;

class Status
{
    // Deliberately public. Protected endpoints should name the required permissions.
    public const PERMISSIONS = ['object' => Permission::ANY, 'get' => Permission::ANY];
    public $data;

    public function get(GETRequest $request): void
    {
        $this->data = ['ok' => true];
    }
}
```

A minimal application bootstrap:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$router = new \obray\core\Router(new \obray\core\Factory(), new \obray\core\Invoker());
$router->addEncoder(\obray\core\encoders\JSONEncoder::class, 'data', 'application/json');
$router->addEncoder(\obray\core\encoders\ErrorEncoder::class, 'error', 'application/json');
$router->setCheckPermissionsHandler(
    new \obray\users\PermissionHandler(new \obray\sessions\Session())
);
$router->route($_SERVER['REQUEST_URI'] ?? ($argv[1] ?? '/'));
```

The application must configure its session user and permissions. A router without
an installed permission handler permits access. Public controller methods can be
addressed by path, so use method-level permission declarations. Keep protected
side effects out of constructors: objects are instantiated before authorization.

`/status` dispatches to the controller's HTTP method (`get()`, `post()`, etc.).
Paths may also select a method, such as `/status/get`. A directory can expose an
`Index` controller. Request-specific parameters such as `POSTRequest` enforce the
HTTP method; `ServerRequest` accepts any supported method. `HEAD` can fall back to
`get()` and emits no body. Query/body parameters bind to named method parameters;
hyphens in names are normalized to underscores.

Only PHP CLI execution gets console privileges. `TENANT`, `PATH`, `argv`-shaped
request data, and an HTTP `CONSOLE` method cannot enable console access.
`OBRAY_FORCE_HTTP_REQUEST` is a trusted test/configuration constant for exercising
HTTP behavior from CLI; it does not grant console privileges.

## Dependency injection and encoders

`Factory` resolves typed constructor dependencies through an optional PSR-11
container, then by constructing concrete classes. Optional scalar values retain
their defaults; injected objects retain their parameter positions. Required
unresolvable dependencies and dependency cycles throw explicit exceptions.

Encoders implement `EncoderInterface`. Register an encoder against the controller
property it encodes: `data` for JSON, `html` for HTML, and so on. The router selects
the first matching public property. Controller methods populate these properties;
their return values are not used as response bodies.

JSON preserves strings, including leading-zero identifiers. PHP numbers remain
numbers. Legacy numeric-string conversion is available explicitly with
`new JSONEncoder(true)`.

Throw `HTTPException` for deliberate HTTP errors. `PermissionDenied` produces 403;
unsupported HTTP methods produce 405. Unexpected exceptions and PHP errors produce
500 responses. Debug output in the bundled bootstrap is enabled only when
`__IS_PRODUCTION__` is explicitly `false`.

## Models and queries

Models extend `obray\data\DBO`. Typed `col_` properties define columns, while
`TABLE`, `INDEXES`, and `FOREIGN_KEYS` define schema metadata. Access values through
the unprefixed names, for example `$product->product_name`, so assignments update
the type wrapper and dirty state. `toArray()` and JSON serialization exclude
password fields.

```php
$products = $querier->select(Product::class)
    ->where(['products.category_id' => [1, 2]])
    ->orderBy(['product_name'])
    ->run();

$product = $querier->select(Product::class)->where(['product_id' => 42])->firstOrNull();
if ($product !== null) {
    $product->product_name = 'Updated name';
    $querier->update($product)->run();
    $querier->delete($product)->run();
}
```

Model writes require a non-negative integer primary key; integer strings and zero
are accepted. UPDATE and DELETE bind the identifier as a value. Deleting an
unsaved model, an empty collection, or multiple models in one call throws before
database execution. Delete collections explicitly, using `DBConn::transaction()`
when the operation must succeed or fail together.

WHERE values are bound independently, including array/OR filters and nested
`AndOp` groups. Empty OR filters match no rows; empty WHERE and AND groups throw.
Generated placeholder names are internal. Column names, ordering expressions,
and `RawSQL` are developer-authored SQL; allowlist any user-selected identifiers.

`DBConn::run()` returns an array of result sets. The higher-level query builder
hydrates models; `firstOrNull()` returns a model or `null`.

`Table` manages model migrations and seeds. Preview changes with `dry_run: true`
and scope them with the `tables` filter. MySQL DDL is not transactional. See
[model trigger migrations](documentation/model-triggers.md) for trigger conflict
handling and targeted repair.

Session properties persist on each assignment. To update an array or object
stored in a session, read it, modify it, and assign it back to the session property.

## Tests and upgrades

```sh
composer test
composer test:migrations
composer test:mysql
```

The full command runs every `tests/*Test.php` script in an isolated PHP process.
Database tests use fakes; SQL safety tests also execute generated SQL against an
in-memory SQLite database when `pdo_sqlite` is available.

`composer test:mysql` starts a separate disposable MySQL server using the locally
installed `mysqld`, Python 3, and PHP with `pdo_mysql`. It validates schema changes,
seeds, query hydration, write guards, transactions, and triggers through the real
database layer, then removes its server and storage. It does not use an existing
database service or application credentials. See [MySQL integration testing](documentation/mysql-testing.md).

See [hardening upgrade notes](documentation/hardening-upgrade.md) before updating
an existing application.
