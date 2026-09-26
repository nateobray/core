<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use obray\core\encoders\ErrorEncoder;
use obray\data\DBConn;
use obray\data\DBStatement;
use obray\data\exceptions\SqlArrayFailedToLoad;
use obray\data\exceptions\SqlFailedToLoad;
use obray\data\exceptions\SqlFileFailedToLoad;
use obray\data\exceptions\SqlFileNotFound;
use obray\data\exceptions\SqlStringFailedToLoad;

$checks = 0;
function load_check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function load_failure(callable $callback, string $type): Throwable {
    try { $callback(); }
    catch (Throwable $error) {
        load_check($error instanceof $type, 'Expected ' . $type . ', got ' . get_class($error));
        load_check($error instanceof SqlFailedToLoad && $error->getMessage() !== '', 'Load errors need a message and a common catch type.');
        return $error;
    }
    throw new RuntimeException('Expected failure: ' . $type);
}
class LoadingDB extends DBConn {
    public int $connections = 0;
    public function __construct(public ?PDO $pdo = null, public ?PDOException $failure = null) {}
    public function getConnection() {
        $this->connections++;
        if ($this->failure !== null) throw $this->failure;
        if ($this->pdo === null) throw new RuntimeException('Invalid input must not connect to a database.');
        return $this->pdo;
    }
}
class InspectLoadingStatement extends DBStatement {
    public function queries(): array { return array_values($this->queryStrings); }
}

$offline = new LoadingDB();
foreach ([null, false, 123, new stdClass()] as $input) {
    load_failure(fn() => (new DBStatement($offline))->loadSql($input), SqlFailedToLoad::class);
}
foreach ([[], ['sql' => null], ['sql' => []], ['sql' => 42], ['sql' => ' ; ; '], ['sql' => "SELECT\0 1"]] as $input) {
    load_failure(fn() => (new DBStatement($offline))->loadSql($input), SqlArrayFailedToLoad::class);
}
foreach (['', "\n \t", '; ;', "SELECT\0 1"] as $input) {
    load_failure(fn() => (new DBStatement($offline))->loadSql($input), SqlStringFailedToLoad::class);
}
load_check($offline->connections === 0, 'Malformed input must fail before connection or preparation.');

$directory = sys_get_temp_dir() . '/obray-sql-load-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$cwd = getcwd();
try {
    chdir($directory);
    file_put_contents('query.sql', 'SELECT 1; SELECT 2;');
    file_put_contents('empty.sql', " ; \n ;");
    file_put_contents('unreadable.sql', 'SELECT 1');
    foreach (['query.sql', './query.sql', $directory . '/query.sql'] as $path) {
        $statement = new InspectLoadingStatement($offline);
        load_check($statement->loadSql($path) === $statement, 'File loading must remain fluent.');
        load_check($statement->queries() === ['SELECT 1', 'SELECT 2'], 'Load existing bare, relative, and absolute filenames.');
    }
    foreach (['missing.sql', './missing.sql', $directory . '/missing.sql', './missing path.sql'] as $path) {
        load_failure(fn() => (new DBStatement($offline))->loadSql($path), SqlFileNotFound::class);
    }
    load_failure(fn() => (new DBStatement($offline))->loadSql('empty.sql'), SqlFileFailedToLoad::class);
    chmod('unreadable.sql', 0000);
    if (!is_readable('unreadable.sql')) {
        $handler = static function () { throw new RuntimeException('Read warning escaped the loader.'); };
        set_error_handler($handler);
        try {
            $error = load_failure(fn() => (new DBStatement($offline))->loadSql('unreadable.sql'), SqlFileFailedToLoad::class);
            load_check($error->getPrevious() instanceof ErrorException, 'Read failures must preserve their cause.');
            $restored = set_error_handler($handler);
            restore_error_handler();
            load_check($restored === $handler, 'File loading must restore the caller error handler.');
        } finally { restore_error_handler(); }
    }
    load_check($offline->connections === 0, 'File handling must not probe the database.');

    $statement = new InspectLoadingStatement($offline);
    $statement->loadSql(['sql' => 'SELECT 1']);
    load_failure(fn() => $statement->loadSql('missing.sql'), SqlFileNotFound::class);
    load_check($statement->queries() === [], 'Failed reload must discard the preceding SQL.');
    load_failure(fn() => $statement->execute(), SqlFailedToLoad::class);
    load_check($offline->connections === 0, 'Failed reload must never execute the preceding query.');
} finally {
    chmod('unreadable.sql', 0600);
    chdir($cwd);
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}

$original = new PDOException('Connection failed: private-host/private-database');
$original->errorInfo = ['HY000', 2002, 'Connection refused'];
$error = load_failure(fn() => (new DBStatement(new LoadingDB(failure: $original)))->loadSql('SELECT 1'), SqlStringFailedToLoad::class);
load_check($error->getPrevious() === $original, 'Preserve the actual connection exception, not a new empty exception.');
$payload = (new ErrorEncoder())->encode($error, microtime(true));
load_check(!str_contains(json_encode($payload), 'private-host') && !str_contains($error->getMessage(), 'SELECT'), 'Public error messages must not include driver details or SQL.');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    foreach ([PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_SILENT] as $mode) {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => $mode]);
        $db = new LoadingDB($pdo);
        $error = load_failure(fn() => (new DBStatement($db))->loadSql('SELEC secret_value'), SqlStringFailedToLoad::class);
        $cause = $error->getPrevious();
        load_check($cause instanceof PDOException && ($cause->errorInfo[0] ?? null) === 'HY000', 'Syntax errors must retain PDO diagnostics in both modes.');
        load_check(!str_contains($error->getMessage(), 'secret_value') && $error->getCode() === 0, 'SQL details and SQLSTATE must not become public messages or HTTP status codes.');

        // Array/file SQL is still prepared at execution, preserving DDL batch behavior.
        $statement = (new DBStatement($db))->loadSql(['sql' => 'SELEC 1']);
        if ($mode === PDO::ERRMODE_SILENT) {
            $failure = load_failure(fn() => $statement->execute(), SqlFailedToLoad::class);
            load_check($failure->getPrevious() instanceof PDOException, 'Silent prepare failures must not become calls on false.');
        } else {
            try { $statement->execute(); throw new RuntimeException('Invalid array SQL was accepted.'); }
            catch (PDOException $e) { load_check($e->errorInfo[0] === 'HY000', 'Execution-time PDO errors must continue to propagate.'); }
        }
        foreach (['SELECT 4 / 2', "SELECT 'a/b.sql'", '/* query.sql */ SELECT 1', '-- query.sql' . "\nSELECT 1"] as $sql) {
            load_check((new DBStatement($db))->loadSql($sql) instanceof DBStatement, 'SQL containing slashes or SQL filenames is not a file path.');
        }
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, name TEXT)');
        $db->run('INSERT INTO records VALUES (:id, :name)', [':id' => 1, ':name' => '000123']);
        load_check($pdo->query('SELECT name FROM records WHERE id = 1')->fetchColumn() === '000123', 'Valid SQL and named bindings must still execute.');
    }
} else {
    echo "SKIP: install pdo_sqlite for real PDO loading checks.\n";
}
echo "SqlLoadingTest passed ($checks checks).\n";
