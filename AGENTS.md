# Repository Guidelines for Code-Generation Agents

This repository contains the reusable core of the Obray PHP framework.  When adding features or debugging code, keep the following mental model in mind so that you interact with the framework correctly.

## Runtime flow overview
1. `core.php` is the canonical bootstrap script.  It wires together a `Factory`, `Invoker`, and optional PSR-11 container, registers output encoders on the `Router`, and then calls `$router->route(...)` for either HTTP requests (`$_SERVER['REQUEST_URI']`) or CLI requests (`$argv[1]`).
2. `Router` (`src/Router.php`) is responsible for translating a URI path into a controller class.  It:
   * Builds a `ServerRequest` instance and uses its exploded path segments to find a matching `controllers\\...` class.
   * Creates controller instances through the `Factory`, then executes the resolved method through the `Invoker`.
   * Selects encoders (JSON/HTML/CSV/Error/Console) based on either controller properties or the request channel.  The encoder encodes the controller (or exception) into the response payload.
   * Outputs an HTTP `Response` or, when in console mode, prints the encoder output directly.
3. `Factory` (`src/Factory.php`) instantiates classes, using constructor type hints to pull dependencies either from the PSR-11 container or by recursively instantiating them.  Missing classes raise `ClassNotFound` or `DependencyNotFound` exceptions.
4. `Invoker` (`src/Invoker.php`) reflects controller methods, normalizes parameter names (hyphen ➜ underscore), resolves default values/type-hinted request objects, and catches `TypeError`s to convert them into meaningful HTTP exceptions when the HTTP verb does not match the method signature.

## Working with controllers
* Controllers are discovered under the `controllers\\` namespace (outside of this repository).  A request path such as `/users/profile` maps to `controllers\\Users\\Profile`, falling back to `controllers\\Users\\Profile\\Index` when the final segment is missing.  Keep this naming contract in mind when adding examples or debugging router behavior.
* The `Router` supports method-level routing.  Query parameters and body data are fed through the `ServerRequest` abstraction before being handed to the controller method.
* Controllers can expose a `$encoder` property to dictate the encoder alias the router should use; otherwise JSON is the default.

## Encoders
Encoders live in `src/encoders/` and implement `obray\core\interfaces\EncoderInterface`.  Each encoder exposes:
* `encode($obj, $start_time, $debug)` – produces a payload from either a controller object (successful request) or an exception.
* `out($encoded)` – used for console output.
* `getContentType()` – used to set the `Content-Type` header when emitting HTTP responses.
Register new encoders through `$router->addEncoder(<EncoderClass>::class, '<alias>', '<mime>')` before calling `route()`.

## ORM and data access
Obray ships with a lightweight ORM under `src/data` that drives migrations and query building for models in downstream
applications (`src/models` in consumer projects) as well as the bundled `obray\users` package.

* **Models extend `obray\data\DBO`.** Define typed properties following the `public <Type> $col_<column>;` convention so that
  `Table::getColumns()` can reflect metadata.  The base `DBO` automatically wraps constructor input in type objects,
  tracks dirty state, exposes `jsonSerialize()`/`toArray()`, and lets you override lifecycle hooks such as
  `onBeforeInsert()`/`onAfterUpdate()` to customize persistence behavior.  When interacting with instances you do not
  need to reference the `$col_` prefix directly—`DBO` proxies `__get()`/`__set()` so `$user->name` transparently maps to
  `$col_name` while still preserving the typed column metadata.
* **Type classes in `src/data/types` control column DDL and value normalization.** Each type class exposes constants like
  `DEFAULT` and helper factories (e.g., `createSQL`) so migrations and runtime values stay consistent.  Use the provided
  subclasses (`PrimaryKey`, `Varchar64`, `BooleanTrue`, etc.) rather than raw scalars when declaring model properties.
* **`obray\data\Table` powers migrations.** Calling `$table->migrate()` scans the consumer project's `src/models`
  namespace for classes that extend `DBO`, honors per-model `FEATURE_SET` flags, and generates tables/indexes/foreign keys
  based on constants such as `TABLE`, `INDEXES`, `FOREIGN_KEYS`, and `SEED_*`.  Existing tables trigger `updateTable()` and
  optional seed refreshes instead of destructive rebuilds.
* **`DBConn`, `Querier`, and `Statement` wrap PDO access.** `DBConn` manages pooled PDO connections and exposes transaction
  helpers.  Inject a `Querier` when you need to build SQL fluently: start with `$querier->select(Model::class)` (or
  `insert/update/delete`) to obtain a `Statement`, then chain `where()`, `join()/leftJoin()`, `orderBy()`, `limit()`, and
  finally call `run()` (or helpers like `runInsertOnEmpty()`/`runUpdateOnExists()`) to execute.  `DBConn::run()` always
  returns an array of result sets—expect `$results[0]` for a single statement—mirroring the PDO multi-query contract.
  Successful selects hydrate `DBO` instances for you, while inserts/updates trigger the model lifecycle hooks described above.

## Error handling conventions
* Application-level issues should throw `obray\core\exceptions\HTTPException` with the correct status code so that the router can select the error encoder and respond gracefully.
* Unexpected exceptions bubble up to the router, which, when an error encoder is registered, will use it and default the status to `500`.
* The `Invoker` specifically recognizes method signatures that require specific HTTP request classes (e.g., `GETRequest`, `POSTRequest`) and will emit a `405 Method Not Allowed` error if the controller method is invoked with the wrong verb.
* The `ErrorEncoder` extends the JSON encoder and produces a consistent structure for front-end consumers:
  * If the controller already exposed an `errors` property, the encoder forwards that array/object verbatim so custom validation payloads survive intact.
  * Otherwise the encoder emits `{ "code": <int>, "error": <string>, "runtime": <float> }`, where `code` comes from the exception code, `error` is the exception message, and `runtime` is the elapsed request time in milliseconds.
  * In non-production environments (the `__IS_PRODUCTION__` constant is defined and `false`) the payload is extended with `line` and `file` fields to simplify debugging.
  * CLI callers receive the decoded `stdClass` instead of a JSON string, matching how other encoders behave in console mode.

## Coding style notes
* This codebase targets PHP 7+ with typed properties/methods where possible.
* Follow PSR-4 naming and keep new source files under `src/` with the `obray\core\...` namespace.
* Avoid suppressing exceptions; allow the router to coordinate error handling.

Refer back to this document whenever you need to remember how routing, invocation, and encoding interact inside the framework.
