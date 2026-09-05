<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/triggerModels/GuardedRecord.php';
require __DIR__ . '/fixtures/triggerModels/OrdinaryRecord.php';

use models\GuardedRecord;
use obray\data\{DBConn, ModelTriggers, Table};

class TriggerTestDB extends DBConn
{
    public array $tables = [];
    public array $triggers = [];
    public array $writes = [];
    public int $reads = 0;
    public bool $failCreate = false;
    public function __construct() {}
    public function query($sql, $bind = [])
    {
        if (str_contains($sql, 'INFORMATION_SCHEMA.TABLES')) {
            $rows = array_map(fn($name) => ['TABLE_NAME' => $name], $this->tables);
        } else {
            $this->writes[] = trim($sql);
            if (preg_match('/CREATE TABLE `([^`]+)`/', $sql, $m)) $this->tables[] = $m[1];
            $rows = [];
        }
        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetch($style = null) { return array_shift($this->rows) ?? false; }
        };
    }
    public function run($sql, $bind = [], $fetchStyle = \PDO::FETCH_OBJ)
    {
        if (str_contains($sql, 'information_schema.TRIGGERS')) {
            $this->reads++;
            return [isset($this->triggers[$bind['name']]) ? [$this->triggers[$bind['name']]] : []];
        }
        $this->writes[] = $sql;
        if ($this->failCreate) throw new RuntimeException('Injected trigger DDL failure');
        if (!preg_match('/^CREATE TRIGGER `([^`]+)` (BEFORE|AFTER) (INSERT|UPDATE|DELETE) ON `([^`]+)` FOR EACH ROW (.*)$/s', $sql, $m)) {
            throw new RuntimeException('Unexpected SQL');
        }
        $this->triggers[$m[1]] = ['EVENT_OBJECT_TABLE' => $m[4], 'EVENT_MANIPULATION' => $m[3],
            'ACTION_TIMING' => $m[2], 'ACTION_STATEMENT' => $m[5]];
        return [];
    }
}

class TriggerTestTable extends Table
{
    // Column/index detection has its own suite. This fixture models an unchanged table.
    protected function updateTable($table, $class) {
        $this->migrationSummary['tables_checked']++;
        $this->migrationSummary['tables_current']++;
    }
    public function fixUserTableOrder(): void {}
    public function summary(): array { return $this->migrationSummary; }
}
$assertions = 0;
$assert = function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$reject = function (callable $call, string $message) use ($assert) {
    try { $call(); } catch (Throwable $e) {
        $assert(str_contains($e->getMessage(), $message), $e->getMessage()); return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
};
$table = fn($db) => new TriggerTestTable($db, __DIR__ . '/fixtures/triggerModels', '', 'test', [1]);
$migrate = function ($db, array $options = []) use ($table) {
    $t = $table($db);
    $t->migrate(...array_merge(['quiet' => true, 'tables' => 'GuardedRecords', 'assume_yes' => true], $options));
    return $t;
};

ob_start();
try {
    if (($argv[1] ?? '') === '--deny') {
        $db = new TriggerTestDB(); $db->tables = ['GuardedRecords'];
        $reject(fn() => $migrate($db, ['assume_yes' => false]), 'cancelled');
        $assert($db->triggers === [], 'Declined confirmation must not install triggers.');
        $assert(str_contains(end($db->writes), 'SET FOREIGN_KEY_CHECKS = @ORIG'), 'Restore settings after cancellation.');
        ob_end_clean(); echo "denial passed\n"; exit(0);
    }
    $db = new TriggerTestDB();
    $plan = (new ModelTriggers($db))->plan(GuardedRecord::class);
    $assert(count($plan) === 2 && $db->writes === [], 'Planning must be read-only.');
    $table($db)->create(GuardedRecord::class, false, false, false);
    $assert(count($db->triggers) === 2 && $db->tables === ['GuardedRecords'], 'Direct creation must install guards.');
    $assert((new ModelTriggers($db))->plan(GuardedRecord::class) === [], 'Installed definitions must match.');
    $db->writes = [];
    $t = $migrate($db);
    $assert($t->summary()['triggers_missing'] === 0, 'Repeat migration must be a no-op for triggers.');
    $assert(count(array_filter($db->writes, fn($sql) => str_starts_with($sql, 'CREATE TRIGGER'))) === 0, 'No repeat trigger DDL.');

    foreach ([[], ['GuardedRecords']] as $existing) {
        $db = new TriggerTestDB(); $db->tables = $existing;
        $t = $migrate($db, ['dry_run' => true]);
        $assert($db->writes === [] && $db->triggers === [], 'Dry run must perform zero writes for new/existing schema.');
        $assert($t->summary()['triggers_missing'] === 2, 'Dry run must report missing protections.');
        $t = $migrate($db);
        $assert(count($db->triggers) === 2, 'Normal migration must install guards for new/existing schema.');
        $assert($t->summary()['triggers_checked'] === 2, 'Summary must count checked triggers.');
        if ($existing !== []) $assert($t->summary()['tables_with_changes'] === 1, 'Guard-only changes must not report table current.');
    }
    $db = new TriggerTestDB(); $db->tables = ['GuardedRecords'];
    $migrate($db, ['seeds_only' => true]);
    $assert($db->writes === [] && $db->reads === 0, 'Seeds-only must not inspect or install triggers.');
    $migrate($db, ['tables' => 'OrdinaryRecords']);
    $assert($db->triggers === [] && $db->reads === 0, 'Table filter and undeclared models must not touch triggers.');

    foreach (['EVENT_OBJECT_TABLE', 'EVENT_MANIPULATION', 'ACTION_TIMING', 'ACTION_STATEMENT'] as $field) {
        $db = new TriggerTestDB();
        $db->triggers['guarded_records_no_delete'] = ['EVENT_OBJECT_TABLE' => 'GuardedRecords',
            'EVENT_MANIPULATION' => 'DELETE', 'ACTION_TIMING' => 'BEFORE',
            'ACTION_STATEMENT' => GuardedRecord::TRIGGERS['guarded_records_no_delete']['statement']];
        $db->triggers['guarded_records_no_delete'][$field] = 'different';
        $reject(fn() => $migrate($db), 'does not match');
        $assert($db->writes === [], 'All definitions must be checked before any schema writes.');
    }
    $db = new TriggerTestDB(); $db->failCreate = true;
    $reject(fn() => $migrate($db), 'Injected trigger');
    $assert(str_contains(end($db->writes), 'SET FOREIGN_KEY_CHECKS = @ORIG'), 'DDL failures must restore session settings.');

    $db = new TriggerTestDB();
    $assert(count($table($db)->migrateTriggers(GuardedRecord::class)) === 2 && $db->writes === [], 'Targeted repair defaults to dry run.');
    $table($db)->migrateTriggers(GuardedRecord::class, false, true);
    $assert(count($db->triggers) === 2, 'Confirmed targeted repair installs guards.');

    $invalid = new class extends GuardedRecord { public const TRIGGERS = ['bad;name' => []]; };
    $reject(fn() => (new ModelTriggers($db))->plan(get_class($invalid)), 'identifier');
    $invalid = new class extends GuardedRecord { public const TRIGGERS = ['invalid' => ['timing' => 'BEFORE', 'event' => 'DROP', 'statement' => 'bad']]; };
    $reject(fn() => (new ModelTriggers($db))->plan(get_class($invalid)), 'definition');
    $invalid = new class extends GuardedRecord { public const TRIGGERS = 'invalid'; };
    $reject(fn() => (new ModelTriggers($db))->plan(get_class($invalid)), 'named array');
    $seedMetadata = new ReflectionMethod(Table::class, 'isSeedMetadataConstant');
    $seedMetadata->setAccessible(true);
    $assert($seedMetadata->invoke($table($db), 'TRIGGERS'), 'Trigger metadata must never be seeded as records.');

    $process = proc_open([PHP_BINARY, __FILE__, '--deny'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fwrite($pipes[0], "n\n"); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $assert(proc_close($process) === 0 && str_contains($output, 'denial passed'), 'Confirmation rejection failed: ' . $error);
} finally { ob_end_clean(); }
echo "Model trigger migrations: {$assertions} assertions passed.\n";
