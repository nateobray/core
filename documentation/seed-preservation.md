# Configurable seed preservation

Seeded model data has three supported update policies. These apply to both constant seeds and CSV seeds.

| Model configuration | Existing rows | Missing rows |
| --- | --- | --- |
| No preservation option | Updated from seeds using existing behavior | Inserted from seeds |
| `SEED_INSERT_ONLY = true` | Entire row preserved | Inserted from seeds |
| `SEED_PRESERVE_COLUMNS = ['column_name']` | Listed fields preserved; other fields updated from seeds | Inserted from seeds, including defaults for protected fields |

`KEEP_SEEDS_CURRENT` still controls whether migrations refresh seeds on existing tables. The new option does not turn seeding on, change row identity, or authorize application edits. `SEED_INSERT_ONLY = true` takes precedence and protects the entire existing row.

## Protecting a configurable name

A two-column status catalog can opt into preserving edited names:

```php
public const SEED_CONSTANTS = true;
public const KEEP_SEEDS_CURRENT = true;
public const SEED_PRESERVE_COLUMNS = ['shipment_status'];
```

Existing names survive subsequent seeding. Missing status IDs still receive their seeded default names. Status IDs and application workflow constants remain fixed.

This policy preserves every existing value in the named field, including null and values that still equal the old default. It does not attempt to infer whether someone edited the field. To distinguish an evolving default from an explicit override, store them separately.

## Keeping both a default and an override

For example, a model may declare a seed-controlled `status_name` and an application-controlled nullable `status_override`:

```php
public const SEED_PRESERVE_COLUMNS = ['status_override'];
```

Seeds may update `status_name` while leaving `status_override` untouched. Application code chooses the effective display name:

```php
$displayName = $row->status_override ?? $row->status_name;
```

The consuming application must supply any new schema column, editing permissions, validation, reset behavior, display-name lookup, and session refresh. The framework does not add an override column or automatically rewrite serialized values. Clearing a nullable override lets the application display the latest default.

For constant seeds, retain the existing convention that the first declared column is the primary key and the second is the generated name; place additional override columns after those two. CSV seeds address fields by header name and continue to honor `SEED_MATCH_COLUMNS`.

## Validation and update behavior

`SEED_PRESERVE_COLUMNS` must be a list of declared database column names, without `col_` prefixes. Unknown columns, primary keys, non-list arrays and non-string entries are rejected before seed database work. An empty list keeps existing behavior. The option is seed metadata and never becomes a constant-seed row.

Preserved fields are omitted from generated UPDATE statements. This avoids overwriting a newer administrator edit with the snapshot read at the start of seeding. Insert operations still initialize protected fields from the seed or the model's normal defaults. Repeated seed runs with unchanged effective seed values issue no further updates.

The underlying statement support is also available as:

```php
$querier->update($model)
    ->preserveUpdateColumns(['status_override'])
    ->run();
```

Normal update lifecycle callbacks remain active. An update with no writable columns is rejected. This API protects the selected fields only; it does not add locking or concurrency protection to other fields.

## Verification

Run `composer run test:seeds` for constant/CSV seed coverage, legacy overwrite and insert-only behavior, matched IDs, missing-row insertion, null preservation, repeated-run behavior, invalid metadata, and an intervening protected-field edit.

Run `composer run test:migrations` for existing migration-detection, trigger and index regressions. These use test doubles. A disposable real-database rehearsal and consuming-application tests remain part of release verification.

The local BM compatibility harness can load the source `Table`, `Statement` and `Update` classes through BM's Composer loader in one CLI process, without replacing BM's installed package. Do not edit installed vendor files as the source of this feature or change tenant status rows merely to test framework connectivity.
