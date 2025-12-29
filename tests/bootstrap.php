<?php

spl_autoload_register(function ($class) {
    $prefixes = [
        'obray\\core\\' => __DIR__ . '/../src/',
        'obray\\data\\' => __DIR__ . '/../src/data/',
        'obray\\containers\\' => __DIR__ . '/../src/container/',
        'obray\\users\\' => __DIR__ . '/../src/users/',
        'obray\\sessions\\' => __DIR__ . '/../src/sessions/',
        'tests\\fixtures\\' => __DIR__ . '/fixtures/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

require_once __DIR__ . '/stubs/psr_stubs.php';
