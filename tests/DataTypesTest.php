<?php
declare(strict_types=1);

namespace {
    require __DIR__ . '/bootstrap.php';

    function assert_type(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    $decimal18_5_sql = obray\data\types\Decimal18_5::createSQL('amount');
    assert_type(
        strpos($decimal18_5_sql, 'DECIMAL(18,5)') !== false,
        'Decimal18_5 should render DECIMAL(18,5).'
    );

    $decimal18_6_sql = obray\data\types\Decimal18_6::createSQL('amount');
    assert_type(
        strpos($decimal18_6_sql, 'DECIMAL(18,6)') !== false,
        'Decimal18_6 should render DECIMAL(18,6).'
    );
    assert_type(
        strpos($decimal18_6_sql, 'DEFAULT 0') !== false,
        'Decimal18_6 should render a numeric default.'
    );

    $decimal18_5_nullable_sql = obray\data\types\Decimal18_5Nullable::createSQL('amount');
    assert_type(
        strpos($decimal18_5_nullable_sql, 'DECIMAL(18,5)') !== false,
        'Decimal18_5Nullable should render DECIMAL(18,5).'
    );
    assert_type(
        strpos($decimal18_5_nullable_sql, 'DEFAULT NULL') !== false,
        'Decimal18_5Nullable should render DEFAULT NULL.'
    );

    echo "Data type tests passed\n";
}
