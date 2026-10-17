<?php
namespace obray\core;

/** Opt-in, bounded aggregates. Start and stop once per request/job. No SQL or bindings. */
final class PerformanceProfile
{
    private static ?array $metrics = null;
    private static int $started = 0;

    public static function start(): void
    {
        self::$metrics = [];
        self::$started = hrtime(true);
    }

    public static function measure(string $phase, callable $operation)
    {
        if (self::$metrics === null) return $operation();
        $start = hrtime(true);
        try {
            return $operation();
        } finally {
            self::add($phase . '_ms', (hrtime(true) - $start) / 1e6);
        }
    }

    public static function add(string $name, float $value = 1): void
    {
        if (self::$metrics !== null) self::$metrics[$name] = (self::$metrics[$name] ?? 0) + $value;
    }

    public static function stop(): array
    {
        if (self::$metrics === null) return [];
        $metrics = self::$metrics;
        $metrics['total_ms'] = (hrtime(true) - self::$started) / 1e6;
        // PHP's peak is process-wide; do not mislabel it as a request peak in workers.
        $metrics['process_peak_bytes'] = memory_get_peak_usage(true);
        self::$metrics = null;
        return $metrics;
    }
}
