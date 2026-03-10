<?php
declare(strict_types=1);

namespace {
    define('OBRAY_FORCE_HTTP_REQUEST', true);
    if (!defined('__BASE_DIR__')) {
        define('__BASE_DIR__', dirname(__DIR__) . '/');
    }
    require __DIR__ . '/bootstrap.php';

    function assert_true($condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function invoke_private_method(object $object, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $args);
    }
}

namespace {

use obray\data\DBConn;
use obray\data\Querier;
use obray\data\Table;
use tests\fixtures\Category;
use tests\fixtures\Product;
use tests\fixtures\SeedConstantInsertOnlyStatus;
use tests\fixtures\SeedConstantMutableStatus;
use tests\fixtures\SeedFileInsertOnlySetting;
use tests\fixtures\SeedFileMutableSetting;

class FakeDBConn extends DBConn
{
    private array $tables = [];
    private array $classMap = [];
    private array $autoIncrement = [];
    private int $lastInsertId = 0;
    private bool $inTransaction = false;

    public function __construct()
    {
        parent::__construct('fake', '', '', 'fake');
    }

    public function connect($reconnect = false)
    {
        return $this;
    }

    public function getConnection()
    {
        return $this;
    }

    public function seed(string $class, array $rows): void
    {
        $table = $class::TABLE;
        $this->classMap[$table] = $class;
        $pk = Table::getPrimaryKey($class);
        $this->tables[$table] = [];
        $max = 0;
        foreach ($rows as $row) {
            if (!isset($row[$pk]) || $row[$pk] === null) {
                $max++;
                $row[$pk] = $max;
            } else {
                $max = max($max, (int)$row[$pk]);
            }
            $this->tables[$table][] = $row;
        }
        $this->autoIncrement[$table] = $max;
    }

    public function run($sql, $bind = [], $fetchStyle = null)
    {
        $sqlTrim = ltrim($sql);
        if (stripos($sqlTrim, 'insert into') === 0) {
            return $this->handleInsert($sqlTrim, $bind);
        }
        if (stripos($sqlTrim, 'update') === 0) {
            return $this->handleUpdate($sqlTrim, $bind);
        }
        if (stripos($sqlTrim, 'select') === 0) {
            return $this->handleSelect($sqlTrim, $bind);
        }
        return [[]];
    }

    public function lastInsertId($name = null)
    {
        return (string)$this->lastInsertId;
    }

    public function beginTransaction()
    {
        $this->inTransaction = true;
    }

    public function commit()
    {
        $this->inTransaction = false;
    }

    public function rollback()
    {
        $this->inTransaction = false;
    }

    public function inTransaction()
    {
        return $this->inTransaction;
    }

    public function query($sql, $bind = [])
    {
        return new class {
            public function fetch()
            {
                return false;
            }
        };
    }

    private function handleInsert(string $sql, array $bind): array
    {
        if (!preg_match('/INSERT INTO\s+`?(\w+)`?\s*\(([^)]+)\)/i', $sql, $matches)) {
            throw new \RuntimeException('Unable to parse insert SQL: ' . $sql);
        }
        $table = $matches[1];
        $columns = array_map(fn($col) => trim($col, " `\t\n\r"), explode(',', $matches[2]));
        $row = [];
        foreach ($columns as $column) {
            $placeholder = ':col_' . $column;
            $row[$column] = $bind[$placeholder] ?? ($bind['col_' . $column] ?? null);
        }
        $pk = Table::getPrimaryKey($this->classMap[$table]);
        if (!isset($row[$pk]) || $row[$pk] === null) {
            $next = ($this->autoIncrement[$table] ?? 0) + 1;
            $this->autoIncrement[$table] = $next;
            $row[$pk] = $next;
        } else {
            $this->autoIncrement[$table] = max($this->autoIncrement[$table] ?? 0, (int)$row[$pk]);
        }
        $this->tables[$table][] = $row;
        $this->lastInsertId = (int)$row[$pk];
        return [[]];
    }

    private function handleUpdate(string $sql, array $bind): array
    {
        if (!preg_match('/UPDATE\s+`?(\w+)`?/i', $sql, $matches)) {
            throw new \RuntimeException('Unable to parse update SQL: ' . $sql);
        }
        $table = $matches[1];
        if (!preg_match('/SET\s+(.*)\s+WHERE\s+(.*)/is', $sql, $setMatches)) {
            throw new \RuntimeException('Unable to parse update SET clause.');
        }
        $setPart = $setMatches[1];
        $wherePart = $setMatches[2];
        $assignments = array_map('trim', explode(',', $setPart));
        $updates = [];
        foreach ($assignments as $assignment) {
            if (preg_match('/`?(\w+)`?\s*=\s*:(\w+)/', $assignment, $a)) {
                $updates[$a[1]] = $bind[':' . $a[2]] ?? ($bind[$a[2]] ?? null);
            }
        }
        $conditions = $this->parseConditions($wherePart, $bind);
        foreach ($this->tables[$table] as &$row) {
            if ($this->rowMatches($row, $conditions, $table)) {
                foreach ($updates as $col => $val) {
                    $row[$col] = $val;
                }
            }
        }
        return [[]];
    }

    private function handleSelect(string $sql, array $bind): array
    {
        $isCount = stripos($sql, 'count(*)') !== false;
        if (!preg_match('/FROM\s+`?(\w+)`?/i', $sql, $fromMatch)) {
            throw new \RuntimeException('Unable to parse FROM clause.');
        }
        $baseAlias = $fromMatch[1];
        $baseTable = $baseAlias;
        $baseClass = $this->classMap[$baseTable] ?? null;
        if ($baseClass === null) {
            throw new \RuntimeException('Unknown base table: ' . $baseTable);
        }

        $conditions = [];
        if (preg_match('/WHERE\s+(.*?)(?:\nORDER BY|\nLIMIT|$)/is', $sql, $whereMatch)) {
            $conditions = $this->parseConditions($whereMatch[1], $bind, $baseAlias);
        }

        $columns = $this->parseSelectColumns($sql);
        $joins = $this->parseJoins($sql);

        $results = [];
        foreach ($this->tables[$baseTable] ?? [] as $baseRow) {
            if (!$this->rowMatches($baseRow, $conditions, $baseAlias)) {
                continue;
            }
            $combinations = [
                [$baseAlias => $baseRow]
            ];
            foreach ($joins as $join) {
                $newCombinations = [];
                $joinRows = $this->tables[$join['table']] ?? [];
                foreach ($combinations as $combo) {
                    $fromRow = $combo[$join['fromAlias']] ?? null;
                    if ($fromRow === null) {
                        $newCombinations[] = $combo + [$join['alias'] => null];
                        continue;
                    }
                    $matches = array_filter($joinRows, function ($row) use ($join, $fromRow) {
                        return $row[$join['toColumn']] == $fromRow[$join['fromColumn']];
                    });
                    if (empty($matches)) {
                        $newCombinations[] = $combo + [$join['alias'] => null];
                    } else {
                        foreach ($matches as $match) {
                            $newCombinations[] = $combo + [$join['alias'] => $match];
                        }
                    }
                }
                $combinations = $newCombinations;
            }

            foreach ($combinations as $combo) {
                if ($isCount) {
                    $results[] = 1;
                    continue;
                }
                $row = [];
                foreach ($columns as $aliasName => $meta) {
                    [$alias, $column] = $meta;
                    $row[$aliasName] = $combo[$alias][$column] ?? null;
                }
                $results[] = $row;
            }
        }

        if ($isCount) {
            return [[['count' => count($results)]]];
        }

        return [$results];
    }

    private function parseConditions(string $wherePart, array $bind, ?string $defaultAlias = null): array
    {
        $conditions = [];
        $parts = preg_split('/\s+AND\s+/i', trim($wherePart));
        foreach ($parts as $part) {
            $part = trim($part, "() \n\r\t");
            if ($part === '') {
                continue;
            }
            if (preg_match('/`?(\w+)`?\.`?(\w+)`?\s*=\s*:(\w+)/', $part, $m)) {
                $conditions[] = [
                    'alias' => $m[1],
                    'column' => $m[2],
                    'value' => $bind[':' . $m[3]] ?? null
                ];
            } elseif (preg_match('/`?(\w+)`?\.`?(\w+)`?\s*=\s*(\d+)/', $part, $m)) {
                $conditions[] = [
                    'alias' => $m[1],
                    'column' => $m[2],
                    'value' => (int)$m[3]
                ];
            } elseif (preg_match('/`?(\w+)`?\s*=\s*:(\w+)/', $part, $m)) {
                $conditions[] = [
                    'alias' => $defaultAlias,
                    'column' => $m[1],
                    'value' => $bind[':' . $m[2]] ?? null
                ];
            } elseif (preg_match('/`?(\w+)`?\s*=\s*(\d+)/', $part, $m)) {
                $conditions[] = [
                    'alias' => $defaultAlias,
                    'column' => $m[1],
                    'value' => (int)$m[2]
                ];
            }
        }
        return $conditions;
    }

    private function rowMatches(array $row, array $conditions, string $alias): bool
    {
        foreach ($conditions as $condition) {
            if ($condition['alias'] !== null && $condition['alias'] !== $alias) {
                continue;
            }
            if (!array_key_exists($condition['column'], $row) || $row[$condition['column']] != $condition['value']) {
                return false;
            }
        }
        return true;
    }

    private function parseSelectColumns(string $sql): array
    {
        $columns = [];
        if (preg_match_all('/`([^`]+)`\.`([^`]+)` AS `([^`]+)`/', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columns[$match[3]] = [$match[1], $match[2]];
            }
        }
        return $columns;
    }

    private function parseJoins(string $sql): array
    {
        $joins = [];
        if (preg_match_all('/JOIN\s+`?(\w+)`?\s+`?(\w+)`?\s+ON\s+`?(\w+)`?\.`?(\w+)`?\s*=\s*`?(\w+)`?\.`?(\w+)`?/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joins[] = [
                    'table' => $match[1],
                    'alias' => $match[2],
                    'toAlias' => $match[3],
                    'toColumn' => $match[4],
                    'fromAlias' => $match[5],
                    'fromColumn' => $match[6]
                ];
            }
        }
        return $joins;
    }
}

$conn = new FakeDBConn();
$conn->seed(Category::class, [
    ['category_id' => 1, 'category_name' => 'Hardware'],
    ['category_id' => 2, 'category_name' => 'Software'],
]);
$conn->seed(Product::class, [
    ['product_id' => 1, 'product_name' => 'Hammer', 'category_id' => 1],
    ['product_id' => 2, 'product_name' => 'IDE', 'category_id' => 2],
]);

$querier = new Querier($conn);

$results = $querier->select(Product::class)
    ->join('categories', Category::class, Product::class, 'category_id', 'category_id')
    ->orderBy('products.product_id')
    ->run();

assert_true(is_array($results) && count($results) === 2, 'Expected two products from select.');
assert_true($results[0]->product_name === 'Hammer', 'First product name mismatch.');
assert_true(isset($results[0]->categories), 'Joined category data missing.');
assert_true(is_array($results[0]->categories), 'Joined category payload not array.');
assert_true(reset($results[0]->categories)->category_name === 'Hardware', 'Joined category name mismatch.');

$first = $querier->select(Product::class)->orderBy('products.product_id')->limit(1)->run();
assert_true($first instanceof Product, 'Limit(1) should return single Product instance.');
assert_true($first->product_name === 'Hammer', 'Limit(1) returned unexpected product.');

$newProduct = new Product(...[
    'product_name' => 'Compiler',
    'category_id' => 2
]);
$newId = $querier->insert($newProduct)->run();
assert_true(is_numeric($newId) && (int)$newId > 0, 'Insert should return new primary key.');
$stored = $querier->select(Product::class)->where(['product_id' => (int)$newId])->limit(1)->run();
assert_true($stored->product_name === 'Compiler', 'Inserted product not persisted.');

$statement = $querier->select(Product::class)->where(['products.product_name' => 'Shell']);
$shell = new Product(...[
    'product_name' => 'Shell',
    'category_id' => 2
]);
$statement->runInsertOnEmpty($shell);
$inserted = $statement->getResults();
assert_true($inserted instanceof Product, 'runInsertOnEmpty should set results to inserted object.');
assert_true($inserted->product_name === 'Shell', 'Inserted object not returned.');
assert_true($querier->select(Product::class)->where(['products.product_name' => 'Shell'])->count() === 1, 'runInsertOnEmpty failed to persist record.');

$updateStmt = $querier->select(Product::class)->where(['products.product_id' => 1]);
$updateStmt->runUpdateOnExists(['product_name' => 'Hammer Pro']);
$updated = $querier->select(Product::class)->where(['products.product_name' => 'Hammer Pro'])->limit(1)->run();
assert_true($updated->product_name === 'Hammer Pro', 'runUpdateOnExists did not apply update.');

$seedFileConn = new FakeDBConn();
$seedFileConn->seed(SeedFileMutableSetting::class, [
    ['setting_id' => 99, 'setting_key' => 'task_filter', 'setting_value' => 'tenant_override'],
]);
$seedFileTable = new Table($seedFileConn);
invoke_private_method($seedFileTable, 'seedFile', [SeedFileMutableSetting::class, false]);
$seedFileQuerier = new Querier($seedFileConn);
$updatedSeedFileSetting = $seedFileQuerier->select(SeedFileMutableSetting::class)->where(['setting_key' => 'task_filter'])->limit(1)->run();
$insertedSeedFileSetting = $seedFileQuerier->select(SeedFileMutableSetting::class)->where(['setting_key' => 'new_setting'])->limit(1)->run();
assert_true($updatedSeedFileSetting->setting_id === 99, 'CSV seeding should reuse the existing primary key for matched rows.');
assert_true($updatedSeedFileSetting->setting_value === 'seed_default', 'Default CSV seeding should update matched rows.');
assert_true($insertedSeedFileSetting instanceof SeedFileMutableSetting, 'CSV seeding should insert missing rows.');
assert_true($insertedSeedFileSetting->setting_value === 'enabled', 'Inserted CSV seed value mismatch.');

$seedFileInsertOnlyConn = new FakeDBConn();
$seedFileInsertOnlyConn->seed(SeedFileInsertOnlySetting::class, [
    ['setting_id' => 105, 'setting_key' => 'task_filter', 'setting_value' => 'tenant_override'],
]);
$seedFileInsertOnlyTable = new Table($seedFileInsertOnlyConn);
invoke_private_method($seedFileInsertOnlyTable, 'seedFile', [SeedFileInsertOnlySetting::class, false]);
$seedFileInsertOnlyQuerier = new Querier($seedFileInsertOnlyConn);
$preservedSeedFileSetting = $seedFileInsertOnlyQuerier->select(SeedFileInsertOnlySetting::class)->where(['setting_key' => 'task_filter'])->limit(1)->run();
$insertedInsertOnlySeedFileSetting = $seedFileInsertOnlyQuerier->select(SeedFileInsertOnlySetting::class)->where(['setting_key' => 'new_setting'])->limit(1)->run();
assert_true($preservedSeedFileSetting->setting_id === 105, 'Insert-only CSV seeding should preserve the existing primary key.');
assert_true($preservedSeedFileSetting->setting_value === 'tenant_override', 'Insert-only CSV seeding should preserve existing matched rows.');
assert_true($insertedInsertOnlySeedFileSetting instanceof SeedFileInsertOnlySetting, 'Insert-only CSV seeding should still insert missing rows.');
assert_true($insertedInsertOnlySeedFileSetting->setting_value === 'enabled', 'Insert-only CSV seed insert value mismatch.');
assert_true($seedFileInsertOnlyQuerier->select(SeedFileInsertOnlySetting::class)->count() === 2, 'Insert-only CSV seeding should keep the existing row and add the missing seed.');

$seedConstantConn = new FakeDBConn();
$seedConstantConn->seed(SeedConstantMutableStatus::class, [
    ['status_id' => 1, 'status_name' => 'Tenant Override Active'],
]);
$seedConstantTable = new Table($seedConstantConn);
invoke_private_method($seedConstantTable, 'seedConstants', [SeedConstantMutableStatus::class, Table::getColumns(SeedConstantMutableStatus::class), false]);
$seedConstantQuerier = new Querier($seedConstantConn);
$updatedSeedConstant = $seedConstantQuerier->select(SeedConstantMutableStatus::class)->where(['status_id' => 1])->limit(1)->run();
$insertedSeedConstant = $seedConstantQuerier->select(SeedConstantMutableStatus::class)->where(['status_id' => 2])->limit(1)->run();
assert_true($updatedSeedConstant->status_name === 'Active', 'Default constant seeding should update matched rows.');
assert_true($insertedSeedConstant instanceof SeedConstantMutableStatus, 'Constant seeding should insert missing seed constants.');
assert_true($insertedSeedConstant->status_name === 'Inactive', 'Inserted constant seed value mismatch.');

$seedConstantInsertOnlyConn = new FakeDBConn();
$seedConstantInsertOnlyConn->seed(SeedConstantInsertOnlyStatus::class, [
    ['status_id' => 1, 'status_name' => 'Tenant Override Active'],
]);
$seedConstantInsertOnlyTable = new Table($seedConstantInsertOnlyConn);
invoke_private_method($seedConstantInsertOnlyTable, 'seedConstants', [SeedConstantInsertOnlyStatus::class, Table::getColumns(SeedConstantInsertOnlyStatus::class), false]);
$seedConstantInsertOnlyQuerier = new Querier($seedConstantInsertOnlyConn);
$preservedSeedConstant = $seedConstantInsertOnlyQuerier->select(SeedConstantInsertOnlyStatus::class)->where(['status_id' => 1])->limit(1)->run();
$insertedInsertOnlySeedConstant = $seedConstantInsertOnlyQuerier->select(SeedConstantInsertOnlyStatus::class)->where(['status_id' => 2])->limit(1)->run();
assert_true($preservedSeedConstant->status_name === 'Tenant Override Active', 'Insert-only constant seeding should preserve existing matched rows.');
assert_true($insertedInsertOnlySeedConstant instanceof SeedConstantInsertOnlyStatus, 'Insert-only constant seeding should still insert missing seed constants.');
assert_true($insertedInsertOnlySeedConstant->status_name === 'Inactive', 'Insert-only constant seed insert value mismatch.');
assert_true($seedConstantInsertOnlyQuerier->select(SeedConstantInsertOnlyStatus::class)->count() === 2, 'Insert-only constant seeding should not treat metadata constants as seed rows.');

echo "Data layer regression tests passed\n";

}
