<?php
declare(strict_types=1);

namespace {
    if (!defined('__BASE_DIR__')) {
        define('__BASE_DIR__', dirname(__DIR__) . '/');
    }
    require __DIR__ . '/bootstrap.php';

    use obray\data\DBConn;
    use obray\data\Table;
    use tests\fixtures\MigrationProduct;

    function assert_mig(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    // ---------------------------------------------------------------------------
    // Infrastructure
    // ---------------------------------------------------------------------------

    /**
     * Minimal DBConn fake that returns injectable SHOW CREATE TABLE DDL strings.
     * All other queries (constraint toggles, etc.) are silently swallowed.
     */
    class MigrationFakeDBConn extends DBConn
    {
        private array $ddlMap = [];

        public function __construct()
        {
            parent::__construct('fake', '', '', 'fake');
        }

        public function connect($reconnect = false) { return $this; }

        public function setShowCreateTable(string $table, string $ddl): void
        {
            $this->ddlMap[$table] = $ddl;
        }

        public function query($sql, $bind = [])
        {
            $trimmed = trim($sql);
            if (stripos($trimmed, 'SHOW CREATE TABLE') !== false) {
                preg_match('/SHOW CREATE TABLE\s+(\w+)/i', $trimmed, $m);
                $table = $m[1] ?? '';
                $ddl = $this->ddlMap[$table] ?? '';
                return new class(['Create Table' => $ddl]) {
                    private bool $fetched = false;
                    public function __construct(private array $row) {}
                    public function fetch(int $style = \PDO::FETCH_ASSOC) {
                        if (!$this->fetched) { $this->fetched = true; return $this->row; }
                        return false;
                    }
                };
            }
            return new class { public function fetch() { return false; } };
        }
    }

    /**
     * Subclass of Table that captures detection calls instead of prompting STDIN.
     * Exposes updateTable() and migrationSummary for assertion.
     */
    class TestableTable extends Table
    {
        public array $addedColumns    = [];
        public array $alteredColumns  = [];
        public array $addedForeignKeys = [];
        public array $addedIndexes    = [];

        public function runUpdateTable(string $table, string $class): void
        {
            $this->updateTable($table, $class);
        }

        public function getSummary(): array
        {
            return $this->migrationSummary;
        }

        protected function addColumn($table, $sql): void
        {
            $this->addedColumns[] = $sql;
        }

        protected function alterTable($table, $sql): void
        {
            $this->alteredColumns[] = $sql;
        }

        protected function addForeignKey($table, $sql = ''): void
        {
            $this->addedForeignKeys[] = $sql;
        }

        protected function addIndex($table, $sql): void
        {
            $this->addedIndexes[] = $sql;
        }
    }

    // ---------------------------------------------------------------------------
    // DDL helpers
    // ---------------------------------------------------------------------------

    function ddlBase(): string
    {
        return "CREATE TABLE `migration_products` (\n"
            . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
            . "  `product_name` varchar(64) NOT NULL,\n"
            . "  `category_id` int(11) unsigned NOT NULL,\n"
            . "  PRIMARY KEY (`product_id`)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    // ---------------------------------------------------------------------------
    // Test 1: Missing column is detected and added
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products',
        "CREATE TABLE `migration_products` (\n"
        . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        . "  `product_name` varchar(64) NOT NULL,\n"
        . "  PRIMARY KEY (`product_id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedColumns) === 1, 'Missing column should trigger addColumn.');
    assert_mig(stripos($t->addedColumns[0], 'category_id') !== false, 'addColumn should reference the missing column.');
    assert_mig($t->getSummary()['columns_missing'] === 1, 'columns_missing count should be 1.');

    // ---------------------------------------------------------------------------
    // Test 2: Type mismatch is detected and altered
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products',
        "CREATE TABLE `migration_products` (\n"
        . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        . "  `product_name` varchar(128) NOT NULL,\n"
        . "  `category_id` int(11) unsigned NOT NULL,\n"
        . "  PRIMARY KEY (`product_id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->alteredColumns) === 1, 'Type mismatch should trigger alterTable.');
    assert_mig(stripos($t->alteredColumns[0], 'product_name') !== false, 'alterTable should reference the mismatched column.');
    assert_mig($t->getSummary()['columns_mismatched'] === 1, 'columns_mismatched count should be 1.');

    // ---------------------------------------------------------------------------
    // Test 3: Missing regular index is detected and added
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products', ddlBase());
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedIndexes) === 2, 'Both missing indexes should be detected.');
    $indexSQL = implode(' ', $t->addedIndexes);
    assert_mig(stripos($indexSQL, 'category_id') !== false, 'Regular index on category_id should be added.');
    assert_mig(stripos($indexSQL, 'UNIQUE') !== false, 'Unique index on product_name should be added.');
    assert_mig($t->getSummary()['indexes_missing'] === 2, 'indexes_missing count should be 2.');

    // ---------------------------------------------------------------------------
    // Test 4: Existing index is not re-added
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products',
        "CREATE TABLE `migration_products` (\n"
        . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        . "  `product_name` varchar(64) NOT NULL,\n"
        . "  `category_id` int(11) unsigned NOT NULL,\n"
        . "  PRIMARY KEY (`product_id`),\n"
        . "  KEY `idx_category_id` (`category_id`) USING BTREE,\n"
        . "  UNIQUE KEY `uniq_product_name` (`product_name`) USING BTREE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedIndexes) === 0, 'Present indexes should not be re-added.');
    assert_mig($t->getSummary()['indexes_missing'] === 0, 'indexes_missing should be 0 when all indexes present.');

    // ---------------------------------------------------------------------------
    // Test 5: Missing foreign key is detected and added
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products', ddlBase());
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedForeignKeys) === 1, 'Missing FK should trigger addForeignKey.');
    assert_mig(stripos($t->addedForeignKeys[0], 'category_id') !== false, 'addForeignKey should reference the missing FK column.');
    assert_mig($t->getSummary()['foreign_keys_missing'] === 1, 'foreign_keys_missing count should be 1.');

    // ---------------------------------------------------------------------------
    // Test 6: Existing foreign key is not re-added
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products',
        "CREATE TABLE `migration_products` (\n"
        . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        . "  `product_name` varchar(64) NOT NULL,\n"
        . "  `category_id` int(11) unsigned NOT NULL,\n"
        . "  PRIMARY KEY (`product_id`),\n"
        . "  CONSTRAINT `fk_migprod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedForeignKeys) === 0, 'Existing FK should not be re-added.');
    assert_mig($t->getSummary()['foreign_keys_missing'] === 0, 'foreign_keys_missing should be 0 when FK already present.');

    // ---------------------------------------------------------------------------
    // Test 7: Clean table — no changes, tables_current incremented
    // ---------------------------------------------------------------------------

    $conn = new MigrationFakeDBConn();
    $conn->setShowCreateTable('migration_products',
        "CREATE TABLE `migration_products` (\n"
        . "  `product_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        . "  `product_name` varchar(64) NOT NULL,\n"
        . "  `category_id` int(11) unsigned NOT NULL,\n"
        . "  PRIMARY KEY (`product_id`),\n"
        . "  KEY `idx_category_id` (`category_id`) USING BTREE,\n"
        . "  UNIQUE KEY `uniq_product_name` (`product_name`) USING BTREE,\n"
        . "  CONSTRAINT `fk_migprod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $t = new TestableTable($conn);
    $t->runUpdateTable('migration_products', MigrationProduct::class);

    assert_mig(count($t->addedColumns)     === 0, 'Clean table should add no columns.');
    assert_mig(count($t->alteredColumns)   === 0, 'Clean table should alter no columns.');
    assert_mig(count($t->addedForeignKeys) === 0, 'Clean table should add no FKs.');
    assert_mig(count($t->addedIndexes)     === 0, 'Clean table should add no indexes.');
    assert_mig($t->getSummary()['tables_current'] === 1, 'Clean table should increment tables_current.');

    echo "Migration detection tests passed\n";
}
