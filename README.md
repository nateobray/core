# Obray Core

A simple PHP framework for building websites and applications.

## Installation

1. Clone the repository.
2. Install dependencies:
   ```bash
   composer install
   ```

## Bootstrap Flow

The entry point `core.php` wires the framework components together:

1. Loads the Composer autoloader and initializes error reporting.
2. Instantiates a `Factory`, `Invoker`, and optional dependency container.
3. Creates a `Router` instance and registers encoders.
4. Routes the incoming HTTP request or CLI command.

## Routing

Routes map URI segments to PHP classes under the `controllers` namespace.  If a
segment does not resolve to a class, the router looks for an `Index` controller
in the current path.  Extra segments are passed to the controller via the
`remaining` parameter.  Requests from the command line bypass permission checks
and default to the console encoder.

## Dependency Injection

The framework uses a factory with an optional PSR-11 container.  The factory
reflects on constructor type hints to resolve dependencies, delegating to the
container when available or recursively instantiating classes.

## Encoders

Available encoders live under `src/encoders`:

- `JSONEncoder`
- `HTMLEncoder`
- `ErrorEncoder`
- `CSVEncoder`
- `ConsoleEncoder`
- `TableEncoder`

Encoders are registered in `core.php` and selected based on request content
negotiation.
