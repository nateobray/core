# Local MySQL integration testing

```sh
composer test:mysql
```

Requirements: a locally installed MySQL server (`mysqld` on PATH), Python 3, and
PHP with `pdo_mysql`. The runner starts its own server process; an existing MySQL
service is not required. It needs permission to launch MySQL and bind a local port.

The runner:

1. Creates a private temporary directory and initializes a new MySQL data directory
   with `--no-defaults`, avoiding existing server configuration and application data.
2. Assigns a random root password through a private startup file, selects an unused
   loopback port, and disables the MySQL X listener and binary logging.
3. Checks the server ID and data-directory identity before creating a randomly
   named `obray_core_test_*` database. The PHP suite refuses to run without this
   disposable-server context.
4. Runs tests through the library's actual `DBConn`, `Statement`, `Querier`, and
   `Table` implementations. This suite uses real PDO/MySQL I/O, not a fake connection.
5. Drops and checks removal of the test database, stops the server, and removes the
   temporary directory. Failure and interruption paths also stop the processes and
   remove their storage; no service is registered with Homebrew or the operating system.

The suite covers:

- Dry-run and filtered model migrations, named indexes, foreign keys, and restored
  connection settings.
- CSV seeds with ID zero and leading-zero strings; repeated migrations and mutable
  seed refreshes without duplication.
- Rejected missing/malformed write IDs, targeted UPDATE/DELETE, ID-zero records,
  quotes and semicolons in bound values, and preservation of neighboring rows.
- Hydrated joins, left joins with no match, independent joined filters, nested
  comparisons, empty OR lists, and NULL/NOT predicates.
- Transaction commit and rollback, including nested work under an outer transaction.
- Real trigger enforcement, unchanged protected rows, and propagated SQLSTATE errors.

To choose another locally installed server or PHP binary:

```sh
OBRAY_TEST_MYSQLD=/path/to/mysqld OBRAY_TEST_PHP=/path/to/php composer test:mysql
```

The runner prints the actual MySQL version. The initial local verification uses
MySQL 9.6.0 and PHP 8.4.12 on macOS. Application rollout should also test the MySQL
version used in that environment. These fixtures validate the framework's database
behavior; they do not replace tests of consuming applications' models and workflows.
