<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use obray\data\DBConn;
use obray\data\DBO;
use obray\data\Querier;
use obray\data\sql\AndOp;
use obray\data\sql\GT;
use obray\data\sql\LT;
use obray\data\sql\Not;
use obray\data\sql\Where;
use obray\data\types\PrimaryKey;
use obray\data\types\Varchar64;

class SafetyRecord extends DBO {
    const TABLE = 'safety_records';
    public PrimaryKey $col_id;
    public Varchar64 $col_name;
}
class CaptureSafetyDB extends DBConn {
    public array $queries = [];
    public function __construct() { parent::__construct('unused', '', '', 'unused'); }
    public function run($sql, $bind = [], $fetchStyle = PDO::FETCH_OBJ) {
        $this->queries[] = [$sql, $bind];
        return [[]];
    }
}
function sql_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = new CaptureSafetyDB();
$querier = new Querier($db);
foreach ([null, '', false, true, [], '1 OR 1=1', '1; DELETE FROM safety_records', 1.5] as $id) {
    foreach (['delete', 'update'] as $action) {
        $model = new SafetyRecord(id: $id, name: 'before');
        $model->name = 'after';
        $caught = false;
        try { $querier->$action($model)->run(); }
        catch (InvalidArgumentException $e) { $caught = true; }
        sql_assert($caught, "$action must reject a missing or malformed primary key.");
        sql_assert($db->queries === [], 'Invalid writes must never reach the database.');
    }
}
foreach ([0, '0', 42, '0042'] as $id) {
    foreach (['delete', 'update'] as $action) {
        $model = new SafetyRecord(id: $id, name: 'before');
        $model->name = 'after';
        $querier->$action($model)->run();
        [$sql, $bind] = array_pop($db->queries);
        sql_assert((bool)preg_match('/WHERE\s+`?id`?\s*=\s*:(\w+)/', $sql, $match), 'Every write must bind its primary-key predicate.');
        sql_assert(($bind[$match[1]] ?? $bind[':' . $match[1]] ?? null) === $id, 'Write binding must preserve the complete ID.');
    }
}
foreach ([[], [new SafetyRecord(id: 1), new SafetyRecord(id: 2)]] as $models) {
    $caught = false;
    try { $querier->delete($models)->run(); }
    catch (InvalidArgumentException $e) { $caught = true; }
    sql_assert($caught && $db->queries === [], 'Ambiguous delete collections must be rejected.');
}

$where = new Where(SafetyRecord::class, ['a.id' => [1, 2], 'b.id' => [3, 4]]);
$sql = $where->toSQL();
sql_assert(array_values($where->values()) === [1, 2, 3, 4], 'Joined filters must retain all bindings.');
sql_assert($where->toSQL() === $sql && count($where->values()) === 4, 'Repeated compilation must be stable.');
$nested = new Where(SafetyRecord::class, ['id' => [new AndOp([new GT(1), new LT(3)]), new AndOp([new GT(5), new LT(7)])]]);
$nestedSql = $nested->toSQL();
sql_assert(array_values($nested->values()) === [1, 3, 5, 7], 'Nested groups must not overwrite bindings.');

// Execute generated predicates on a disposable engine when available, beyond inspecting SQL text.
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE safety_records (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO safety_records VALUES (0, 'zero'), (1, 'one'), (2, 'two'), (3, NULL), (6, 'six')");
    $statement = $pdo->prepare('SELECT a.id, b.id FROM safety_records a CROSS JOIN safety_records b ' . $sql);
    $statement->execute($where->values());
    sql_assert($statement->fetchAll(PDO::FETCH_NUM) === [[1, 3], [2, 3]], 'Joined filtering must return the intended records.');
    $statement = $pdo->prepare('SELECT id FROM safety_records ' . $nestedSql . ' ORDER BY id');
    $statement->execute($nested->values());
    sql_assert($statement->fetchAll(PDO::FETCH_COLUMN) === [2, 6], 'Nested predicates must retain their original ranges.');
    $not = new Where(SafetyRecord::class, ['name' => new AndOp([new Not(null), new Not('two')])]);
    $statement = $pdo->prepare('SELECT id FROM safety_records ' . $not->toSQL() . ' ORDER BY id');
    $statement->execute($not->values());
    sql_assert($statement->fetchAll(PDO::FETCH_COLUMN) === [0, 1, 6], 'Grouped NOT predicates must handle NULL and ordinary values.');
    $empty = new Where(SafetyRecord::class, ['id' => []]);
    sql_assert($pdo->query('SELECT id FROM safety_records ' . $empty->toSQL())->fetchAll() === [], 'An empty OR filter must match no records.');
    $model = new SafetyRecord(id: 2, name: 'two');
    $model->name = 'updated';
    $querier->update($model)->run();
    [$updateSql, $updateBind] = array_pop($db->queries);
    $pdo->prepare($updateSql)->execute($updateBind);
    sql_assert($pdo->query("SELECT id FROM safety_records WHERE name = 'updated'")->fetchAll(PDO::FETCH_COLUMN) === [2], 'Update must affect only the specified record.');
    $querier->delete(new SafetyRecord(id: 0))->run();
    [$deleteSql, $deleteBind] = array_pop($db->queries);
    $pdo->prepare($deleteSql)->execute($deleteBind);
    sql_assert($pdo->query('SELECT id FROM safety_records ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === [1, 2, 3, 6], 'Delete must affect only the specified row, including ID zero.');
} else {
    echo "SQLite execution checks skipped (pdo_sqlite unavailable); SQL guard and binding assertions still ran.\n";
}
echo "SQL safety tests passed\n";
