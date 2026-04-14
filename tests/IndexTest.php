<?php
declare(strict_types=1);

namespace {
    if (!defined('__BASE_DIR__')) {
        define('__BASE_DIR__', dirname(__DIR__) . '/');
    }
    require __DIR__ . '/bootstrap.php';

    function assert_true_idx(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    use obray\data\sql\Index;

    // --- createSQL: deterministic names ---

    $sql = Index::createSQL('name');
    assert_true_idx(strpos($sql, 'idx_name') !== false, 'Single column index should produce idx_name key.');
    assert_true_idx(strpos($sql, 'UNIQUE') === false, 'Non-unique index should not contain UNIQUE.');

    $sql2 = Index::createSQL('name');
    assert_true_idx($sql === $sql2, 'createSQL should be deterministic — same input same output.');

    $sql3 = Index::createSQL('name', Index::UNIQUE);
    assert_true_idx(strpos($sql3, 'uniq_name') !== false, 'Unique single column should produce uniq_name key.');
    assert_true_idx(strpos($sql3, 'UNIQUE') !== false, 'Unique index should contain UNIQUE keyword.');

    $sql4 = Index::createSQL(['col1', 'col2']);
    assert_true_idx(strpos($sql4, 'idx_col1_col2') !== false, 'Multi-column index should join column names.');

    // --- createSQL: truncation at 64 chars ---

    $longCols = ['analytic_type_id', 'analytic_date', 'customer_id', 'category_id', 'brand_id', 'item_id', 'sales_channel_id'];
    $longSql = Index::createSQL($longCols, Index::UNIQUE);
    preg_match('/KEY `([^`]+)`/', $longSql, $matches);
    $keyName = $matches[1] ?? '';
    assert_true_idx(strlen($keyName) <= 64, 'Generated key name must not exceed 64 characters (MySQL limit).');
    assert_true_idx(strlen($keyName) > 0, 'Key name must not be empty after truncation.');

    $longSql2 = Index::createSQL($longCols, Index::UNIQUE);
    assert_true_idx($longSql === $longSql2, 'Truncated key name must still be deterministic.');

    // --- normalize: single column no type ---

    $n = Index::normalize(['col_name']);
    assert_true_idx($n['columns'] === ['col_name'], 'Single column entry should parse correctly.');
    assert_true_idx($n['type'] === '', 'Single column entry with no type should default to empty string.');

    // --- normalize: single column with UNIQUE ---

    $n = Index::normalize(['col_name', 'UNIQUE']);
    assert_true_idx($n['columns'] === ['col_name'], 'Single column + UNIQUE should parse column correctly.');
    assert_true_idx($n['type'] === 'UNIQUE', 'Single column + UNIQUE should parse type correctly.');

    // --- normalize: explicit columns array + type ---

    $n = Index::normalize([['col1', 'col2'], 'UNIQUE']);
    assert_true_idx($n['columns'] === ['col1', 'col2'], 'Explicit array + type should parse columns correctly.');
    assert_true_idx($n['type'] === 'UNIQUE', 'Explicit array + type should parse type correctly.');

    // --- normalize: flat multi-column composite (no type) ---

    $n = Index::normalize(['col1', 'col2', 'col3']);
    assert_true_idx($n['columns'] === ['col1', 'col2', 'col3'], 'Flat multi-column should treat all elements as columns.');
    assert_true_idx($n['type'] === '', 'Flat multi-column should have empty type.');

    // --- normalize: flat two-column where second looks like a column not a type ---

    $n = Index::normalize(['task_type_id', 'task_status_id']);
    assert_true_idx($n['columns'] === ['task_type_id', 'task_status_id'], 'Two-column flat entry should not misinterpret second element as type.');
    assert_true_idx($n['type'] === '', 'Two-column flat entry should have empty type.');

    // --- normalize: type is case-insensitive ---

    $n = Index::normalize(['col_name', 'unique']);
    assert_true_idx($n['type'] === 'UNIQUE', 'normalize() should uppercase the type.');

    echo "Index tests passed\n";
}
