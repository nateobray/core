# Runtime hardening upgrade notes

These changes are in the core library checkout. Consuming applications need a
published dependency update and their own verification before these fixes affect
their runtime.

## Changes that may affect applications

- HTTP request parameters no longer select console execution. Any HTTP worker or
  internal bridge that relied on `TENANT`/`PATH` granting console privileges must
  use an authenticated and authorized HTTP endpoint, or invoke the real CLI.
  Tenant selection is an application concern and does not confer authorization.
  HTTP methods are allowlisted; `CONSOLE` and unsupported verbs return 405.
- Default JSON encoding preserves strings. Applications that relied on implicit
  numeric-string conversion should normalize the relevant fields themselves, or
  explicitly construct `JSONEncoder(true)` for the previous behavior. Error
  payloads also preserve strings. Existing `JSONEncoder(false)` configuration
  retains its behavior.
- UPDATE and DELETE require non-negative integer identifiers, accepting both PHP
  integers and digit strings, including zero. Values are parameterized. DELETE
  rejects missing IDs and empty/multiple-model arrays; a one-model array remains
  accepted. The old implementation silently used only the first array member.
- WHERE placeholders are internal and now unique per value occurrence. Do not
  depend on generated placeholder names. Empty OR arrays match nothing; empty
  WHERE or AND groups throw. Grouped `Not(null)` and non-null `Not` comparisons
  now use their proper SQL operators.
- Session assignments no longer create shadow properties. Repeated assignment,
  unsetting, and destruction use the persisted session state. Assign modified
  session objects/arrays back to their property to persist changes.
- Factory injects dependencies by parameter name, preserving skipped defaults.
  Optional unregistered interface dependencies retain their defaults; unconfigured
  variadic dependencies remain empty. Concrete classes can be autowired when a
  supplied container does not have an entry.
- Request object parameters are injected from the actual request rather than
  client-supplied fields. Boolean inputs and explicit `null` values are preserved.
  Missing required parameters return 400. Boundary argument type errors retain
  406; wrong request types return 405. Type errors inside controllers return 500.
  `PermissionDenied` now returns HTTP 403, and the router catches PHP `Error` and
  request/encoder failures. Reusing a router clears the preceding response state.
- The bundled bootstrap defaults to hidden PHP error display and emits CLI
  output. Declare `__IS_PRODUCTION__ = false` to enable its development display.
- Composer now accurately requires PHP 8.0 or later. Run `composer test` for the
  complete regression suite, including authorization and SQL write boundaries.
- Model migrations no longer automatically call the BM-specific
  `fixUserTableOrder()` repair. Applications that require that legacy column-order
  change can still call the public method explicitly against a compatible schema.
  Filtered migrations now stay within their selected models. New tables honor
  explicit index names, and migration cleanup restores the original time zone.

## Verification before an application release

Run the library tests and the consuming application's own tests. Exercise a
protected HTTP route with and without `TENANT` and confirm both require the same
authorization. Check actual CLI workers, ID-zero records, numeric-looking
identifiers in API responses, session updates, and dynamic joined filters.
Run `composer test:mysql` for the disposable local MySQL integration suite, then
test the consuming application's concrete models on its target MySQL version.
SQLite query checks alone do not establish MySQL migration compatibility.
