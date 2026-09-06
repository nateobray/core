<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
foreach (glob(__DIR__ . '/fixtures/*.php') as $fixture) require $fixture;

use models\Category;
use models\Guarded;
use models\Product;
use models\Setting;
use obray\data\DBConn;
use obray\data\ModelTriggers;
use obray\data\Querier;
use obray\data\Table;
use obray\data\sql\AndOp;
use obray\data\sql\GT;
use obray\data\sql\LT;
use obray\data\sql\Not;

$checks = 0;
function check_mysql(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function expect_mysql_failure(callable $callback, string $class, string $message): Throwable {
    try { $callback(); }
    catch (Throwable $error) {
        check_mysql($error instanceof $class, $message . ' (unexpected exception: ' . get_class($error) . ')');
        return $error;
    }
    throw new RuntimeException($message . ' (operation unexpectedly succeeded)');
}
function migrate_mysql(DBConn $db, string $database, bool $dry = false): string {
    $table = new Table($db, __DIR__ . '/fixtures', __DIR__ . '/seeds/', $database, [1]);
    ob_start();
    try {
        $table->migrate(dry_run: $dry, quiet: true, tables: 'mysql_categories,mysql_products,mysql_settings,mysql_guarded', assume_yes: true);
        return ob_get_contents();
    } finally { ob_end_clean(); }
}
function rows_mysql(DBConn $db): array {
    return $db->run('SELECT product_id, name, category_id FROM mysql_products ORDER BY product_id', [], PDO::FETCH_ASSOC)[0];
}
function session_mysql(DBConn $db): array {
    return $db->run('SELECT @@SESSION.sql_mode AS sql_mode, @@SESSION.foreign_key_checks AS fk, @@SESSION.unique_checks AS uq, @@SESSION.time_zone AS tz', [], PDO::FETCH_ASSOC)[0][0];
}

$port = getenv('OBRAY_MYSQL_TEST_PORT');
$password = getenv('OBRAY_MYSQL_TEST_PASSWORD');
$database = getenv('OBRAY_MYSQL_TEST_DATABASE');
if (!ctype_digit((string)$port) || !$password || !preg_match('/^obray_core_test_[a-f0-9]{16}$/D', (string)$database)) {
    throw new RuntimeException('Run this suite through composer test:mysql; an isolated server is required.');
}
$admin = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $admin->query('SELECT @@server_id AS id, @@datadir AS datadir, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
check_mysql((string)$identity['id'] === getenv('OBRAY_MYSQL_TEST_SERVER_ID')
    && realpath($identity['datadir']) === realpath((string)getenv('OBRAY_MYSQL_TEST_DATADIR')),
    'Refusing to use a database server not created by the disposable runner.');
$admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$db = new DBConn('127.0.0.1', 'root', $password, $database, $port, 'InnoDB', 'utf8mb4');
try {
    $querier = new Querier($db);
    $originalSession = session_mysql($db);
    migrate_mysql($db, $database, true);
    check_mysql($admin->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database'")->fetchColumn() == 0, 'Dry-run migration must create no tables.');
    migrate_mysql($db, $database);
    check_mysql(session_mysql($db) === $originalSession, 'Migrations must restore session settings.');
    $tables = $admin->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    check_mysql($tables === ['mysql_categories', 'mysql_guarded', 'mysql_products', 'mysql_settings'], 'Table filtering must limit schema changes to selected models.');
    $indexes = $db->run('SHOW INDEX FROM mysql_products', [], PDO::FETCH_ASSOC)[0];
    check_mysql(in_array('product_name_unique', array_column($indexes, 'Key_name'), true), 'New-table migrations must honor explicit index names.');
    $settings = $querier->select(Setting::class)->orderBy('setting_id')->run();
    check_mysql(count($settings) === 2 && $settings[0]->setting_id === 0 && $settings[0]->setting_value === '000123', 'CSV seeding must preserve ID zero and identifier strings.');

    foreach ([1 => 'first', 2 => 'second', 3 => 'third'] as $id => $name) {
        $querier->insert(new Category(category_id: $id, name: $name))->run();
    }
    foreach ([1 => 1, 2 => 2, 3 => 3, 6 => null] as $id => $category) {
        $querier->insert(new Product(product_id: $id, name: 'product-' . $id, category_id: $category))->run();
    }
    $db->run("SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO')");
    try {
        $db->run("INSERT INTO mysql_products (product_id, name, category_id) VALUES (0, 'zero', 1)");
    } finally {
        $db->run('SET SESSION sql_mode = :mode', ['mode' => $originalSession['sql_mode']]);
    }
    $snapshot = rows_mysql($db);
    foreach ([null, '', false, true, [], '1 OR 1=1', '1; DELETE FROM mysql_products', 1.5] as $id) {
        foreach (['update', 'delete'] as $action) {
            $model = new Product(product_id: $id, name: 'bad', category_id: null);
            $model->name = 'rejected';
            expect_mysql_failure(fn() => $querier->$action($model)->run(), InvalidArgumentException::class, 'Invalid write identifiers must be rejected.');
            check_mysql(rows_mysql($db) === $snapshot, 'Rejected writes must preserve all rows.');
        }
    }
    foreach ([0, '0'] as $zero) {
        $model = $querier->select(Product::class)->where(['product_id' => $zero])->firstOrNull();
        check_mysql($model instanceof Product, 'ID zero must hydrate as a real model.');
        $model->name = 'zero-updated';
        $querier->update($model)->run();
        check_mysql($db->run('SELECT name FROM mysql_products WHERE product_id = 0', [], PDO::FETCH_ASSOC)[0][0]['name'] === 'zero-updated', 'UPDATE must target ID zero.');
    }
    $querier->delete(new Product(product_id: '0'))->run();
    check_mysql(array_column(rows_mysql($db), 'product_id') === [1, 2, 3, 6], 'DELETE must remove only ID zero.');
    $product = $querier->select(Product::class)->where(['product_id' => 2])->firstOrNull();
    $product->name = "000456; O'Reilly";
    $querier->update($product)->run();
    check_mysql($querier->select(Product::class)->where(['product_id' => 2])->firstOrNull()->name === "000456; O'Reilly", 'Bound update values must preserve quotes and semicolons.');
    check_mysql(count(rows_mysql($db)) === 4, 'Targeted updates must preserve neighboring rows.');

    $joined = $querier->select(Product::class)->join('category', Category::class)
        ->where(['mysql_products.category_id' => [1, 2], 'category.category_id' => [2, 3]])->run();
    check_mysql(count($joined) === 1 && $joined[0]->product_id === 2 && $joined[0]->category[0]->category_id === 2, 'Joined filtering and hydration must preserve independent bindings.');
    $left = $querier->select(Product::class)->leftJoin('category', Category::class)->where(['product_id' => 6])->firstOrNull();
    check_mysql($left instanceof Product && $left->category === [], 'Left joins must preserve records with no related row.');
    $nested = $querier->select(Product::class)->where(['product_id' => [new AndOp([new GT(1), new LT(3)]), new AndOp([new GT(5), new LT(7)])]])->orderBy('product_id')->run();
    check_mysql(array_map(fn($row) => $row->product_id, $nested) === [2, 6], 'Nested ranges must retain their own values.');
    check_mysql($querier->select(Product::class)->where(['product_id' => []])->run() === [], 'An empty OR filter must return no rows.');
    check_mysql($querier->select(Product::class)->where(['category_id' => new AndOp([new Not(null), new Not(1)])])->count() === 2, 'Grouped NOT and NULL predicates must work in MySQL.');

    $beforeTransaction = rows_mysql($db);
    expect_mysql_failure(function () use ($db, $querier) {
        $db->transaction(function () use ($db, $querier) {
            $querier->delete(new Product(product_id: 1))->run();
            $db->transaction(fn() => $querier->insert(new Product(name: 'rolled-back'))->run());
            throw new RuntimeException('Rollback test');
        });
    }, RuntimeException::class, 'Transaction failure must propagate.');
    check_mysql(rows_mysql($db) === $beforeTransaction && !$db->inTransaction(), 'Outer transaction failure must roll back all writes.');
    $committed = $db->transaction(fn() => $querier->insert(new Product(name: 'committed'))->run());
    check_mysql($querier->select(Product::class)->where(['product_id' => $committed])->firstOrNull()->name === 'committed', 'Successful transactions must persist writes.');
    $error = expect_mysql_failure(fn() => $querier->insert(new Product(name: 'invalid-fk', category_id: 999))->run(), PDOException::class, 'Foreign keys must reject invalid references.');
    check_mysql($error->getCode() === '23000', 'Constraint failures must retain SQLSTATE.');

    $querier->insert(new Guarded(id: 1, name: 'original'))->run();
    foreach (['update', 'delete'] as $action) {
        $guarded = new Guarded(id: 1, name: 'original');
        $guarded->name = 'changed';
        $error = expect_mysql_failure(fn() => $querier->$action($guarded)->run(), PDOException::class, 'Database triggers must block protected writes.');
        check_mysql($error->getCode() === '45000', 'Trigger failures must retain SQLSTATE.');
    }
    check_mysql($querier->select(Guarded::class)->firstOrNull()->name === 'original', 'Rejected trigger writes must preserve the stored row.');
    check_mysql((new ModelTriggers($db))->plan(Guarded::class) === [], 'Installed triggers must match the model.');

    $setting = $querier->select(Setting::class)->where(['setting_key' => 'mode'])->firstOrNull();
    $setting->setting_value = 'changed';
    $querier->update($setting)->run();
    migrate_mysql($db, $database);
    $settings = $querier->select(Setting::class)->orderBy('setting_id')->run();
    check_mysql(count($settings) === 2 && $settings[0]->setting_id === 0 && $settings[1]->setting_value === 'default', 'Repeated migrations must refresh mutable seeds without duplicating them.');
    $afterMigration = rows_mysql($db);
    $second = migrate_mysql($db, $database, true);
    check_mysql(!str_contains($second, 'ALTER TABLE') && !str_contains($second, 'CREATE TRIGGER'), 'A repeated dry run must detect an up-to-date schema.');
    check_mysql(rows_mysql($db) === $afterMigration && session_mysql($db) === $originalSession, 'Repeated migrations must preserve application rows and connection settings.');
} finally {
    if ($db->inTransaction()) $db->rollback();
    $db->disconnect();
    $admin->exec("DROP DATABASE `$database`");
    check_mysql($admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$database'")->fetchColumn() == 0, 'The disposable test database must be removed.');
}
echo "MySQL {$identity['version']}: $checks integration checks passed.\n";
