<?php
// Tests run in separate PHP processes because their fixtures and constants are isolated per script.
$failed = [];
$tests = glob(__DIR__ . '/*Test.php');
sort($tests);
foreach ($tests as $test) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test), $status);
    if ($status !== 0) $failed[] = basename($test);
}
echo sprintf("\n%d/%d test scripts passed.\n", count($tests) - count($failed), count($tests));
if ($failed !== []) fwrite(STDERR, 'Failed: ' . implode(', ', $failed) . "\n");
exit($failed === [] ? 0 : 1);
