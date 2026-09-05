# Model-owned database triggers

Models can declare MySQL triggers alongside `TABLE`, `INDEXES`, and `FOREIGN_KEYS`:

```php
public const TRIGGERS = [
    'audit_events_no_update' => [
        'timing' => 'BEFORE',
        'event' => 'UPDATE',
        'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit history is immutable'",
    ],
    'audit_events_no_delete' => [
        'timing' => 'BEFORE',
        'event' => 'DELETE',
        'statement' => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit history is immutable'",
    ],
];
```

Names must be unique within the database, use letters/digits/underscores, start
with a letter/underscore, and be at most 64 characters. Timing is `BEFORE` or
`AFTER`; event is `INSERT`, `UPDATE`, or `DELETE`. The statement is trusted,
developer-authored SQL, never request input. Supply only the trigger body, without
client `DELIMITER` directives or an environment-specific `DEFINER`.

## Migration behavior

- `Table::create(Model::class)` creates the declared triggers as part of creating
  a new table. Like its existing table creation behavior, this is an explicit
  write operation without a separate prompt.
- `Table::migrate()` checks triggers on both new and existing selected models,
  including tables whose columns and indexes are unchanged. Existing-table
  repairs honor migration confirmation or `assume_yes`/`yes`.
- Dry runs show missing trigger SQL without writing anything. Table filters and
  feature selection apply normally. `seeds_only` skips trigger work; `skip_seeds`
  does not skip triggers. Trigger metadata is excluded from constant-based seeds.
- The summary counts checked and missing triggers. Matching triggers are no-ops.
- Existing definitions must match the model's table, event, timing, and body.
  Only leading/trailing body whitespace is ignored. Other differences fail
  closed with an explicit conflict error, including names owned by another table.
  All declarations for a selected model are checked before changing its table.
- Unrelated triggers are untouched. Removing a declaration does **not** drop a
  database trigger. Changes/removals require a separately reviewed migration;
  the framework never silently drops or replaces a protection.
- A cancelled trigger repair or failed DDL throws, rather than reporting success.
  Temporary table-creation session settings are restored on return or failure.

The migration user needs MySQL `TRIGGER` privileges, including visibility into
existing definitions. MySQL DDL is not transactional. A permissions error or
interruption can leave partial progress; inspect a fresh dry run before retrying.
These triggers protect row-level UPDATE/DELETE, not administrator DDL such as
TRUNCATE or DROP. Restrict application database privileges accordingly.

## Shared planning and targeted repair

```php
// Read-only readiness check, scoped to the connection's current database.
$missing = (new \obray\data\ModelTriggers($db))->plan(AuditEvent::class);

// Optional compatibility/repair entry point, defaults to dry-run.
$table->migrateTriggers(AuditEvent::class);
// Explicitly approved repair on an existing table:
$table->migrateTriggers(AuditEvent::class, dry_run: false, assume_yes: true);
```

Publish this framework version and update consuming applications' Composer
dependency before relying on model declarations. Older versions do not recognize
`TRIGGERS`. A local change to an installed `vendor/` copy is not a release.

## Verification

```sh
composer test:migrations
```

`ModelTriggersTest.php` exercises public create/migrate paths with fake database
I/O, including missing/current/conflicting definitions, dry runs, filters,
confirmation rejection, error propagation, and session cleanup. Consumers should
also test their concrete models on a disposable MySQL database. ORCA's
`composer test:ledger:mysql` provides real migration and row-protection coverage.
