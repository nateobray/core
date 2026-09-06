<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use obray\sessions\Session;

function session_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$directory = sys_get_temp_dir() . '/obray-session-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Unable to create isolated session storage.');
ini_set('session.save_path', $directory);
session_id(bin2hex(random_bytes(16)));
try {
    $session = new Session();
    $session->value = 'first';
    $session->value = 'second';
    session_assert((new Session())->value === 'second', 'Every assignment must persist.');
    $session->value = false;
    session_assert((new Session())->value === false, 'False session values must persist.');
    session_assert(isset($session->value), 'Persisted false values must still be set.');
    unset($session->value);
    session_assert(!isset($session->value) && (new Session())->value === null, 'Unset must remove persisted values.');
    $session->value = 'third';
    $session->destroy();
    session_assert($session->get() == new stdClass(), 'Destroy must remove persisted and in-memory session data.');
    $session->destroy();
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    foreach (glob($directory . '/sess_*') as $file) unlink($file);
    rmdir($directory);
}
echo "Session tests passed\n";
