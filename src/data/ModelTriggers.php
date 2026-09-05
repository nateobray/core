<?php
declare(strict_types=1);
namespace obray\data;

/** Plans model-owned MySQL triggers without executing DDL or replacing existing triggers. */
final class ModelTriggers
{
    private DBConn $db;

    public function __construct(DBConn $db) { $this->db = $db; }

    public function plan(string $class): array
    {
        $definitions = defined($class . '::TRIGGERS') ? $class::TRIGGERS : [];
        if (!is_array($definitions)) throw new \InvalidArgumentException('TRIGGERS must be a named array.');
        if ($definitions === []) return [];
        $this->identifier($class::TABLE);
        $validated = [];
        foreach ($definitions as $name => $definition) {
            $this->identifier($name);
            if (!is_array($definition) || count($definition) !== 3
                || !in_array($definition['timing'] ?? null, ['BEFORE', 'AFTER'], true)
                || !in_array($definition['event'] ?? null, ['INSERT', 'UPDATE', 'DELETE'], true)
                || !is_string($definition['statement'] ?? null) || trim($definition['statement']) === '') {
                throw new \InvalidArgumentException("Invalid trigger definition: {$name}.");
            }
            if (isset($validated[strtolower($name)])) throw new \InvalidArgumentException('Duplicate trigger name.');
            $validated[strtolower($name)] = [$name, $definition];
        }
        $planned = [];
        foreach ($validated as [$name, $definition]) {
            $existing = $this->db->run(
                'SELECT EVENT_OBJECT_TABLE, EVENT_MANIPULATION, ACTION_TIMING, ACTION_STATEMENT '
                . 'FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :name',
                ['name' => $name], \PDO::FETCH_ASSOC
            )[0] ?? [];
            $body = trim($definition['statement']);
            if ($existing !== []) {
                $trigger = $existing[0];
                if (count($existing) !== 1 || $trigger['EVENT_OBJECT_TABLE'] !== $class::TABLE
                    || $trigger['EVENT_MANIPULATION'] !== $definition['event']
                    || $trigger['ACTION_TIMING'] !== $definition['timing']
                    || trim($trigger['ACTION_STATEMENT']) !== $body) {
                    throw new \DomainException("Existing trigger {$name} does not match the model. Review it manually.");
                }
                continue;
            }
            $planned[] = "CREATE TRIGGER `{$name}` {$definition['timing']} {$definition['event']} ON `"
                . $class::TABLE . '` FOR EACH ROW ' . $body;
        }
        return $planned;
    }

    private function identifier($value): void
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $value)) {
            throw new \InvalidArgumentException('Invalid model trigger or table identifier.');
        }
    }
}
