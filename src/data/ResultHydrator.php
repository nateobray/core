<?php
namespace obray\data;

/** A fresh identity map per result graph, with children scoped to their parent. */
final class ResultHydrator
{
    public function hydrate(array $rows, string $class, array $joins): array
    {
        if ($rows === []) return [];
        $plan = $this->plan($class, $class::TABLE, $joins, array_keys($rows[0]));
        $results = [];
        foreach ($rows as $row) {
            if ($row !== []) $this->append($results, $row, $plan, false);
        }
        $this->normalize($results, $plan);
        return $results;
    }

    private function plan(string $class, string $alias, array $joins, array $keys): array
    {
        $columns = [];
        $prefix = $alias . '_';
        foreach ($keys as $key) {
            if (strpos($key, $prefix) === 0) $columns[$key] = substr($key, strlen($prefix));
        }
        $children = [];
        foreach ($joins as $join) {
            $children[$join->getName()] = $this->plan($join->getToClass(), $join->getName(), $join->joins, $keys);
        }
        return ['class' => $class, 'key' => $prefix . $class::getPrimaryKey(), 'columns' => $columns, 'children' => $children];
    }

    private function append(array &$objects, array $row, array $plan, bool $child): void
    {
        $key = $row[$plan['key']] ?? null;
        // Match DBO::empty(), including its treatment of integer zero.
        if ($child && empty($key) && $key !== 0) return;
        if (!isset($objects[$key])) {
            $props = [];
            foreach ($plan['columns'] as $column => $property) $props[$property] = $row[$column];
            $object = new ($plan['class'])(...$props);
            if ($child && $object->empty()) return;
            $objects[$key] = $object;
            \obray\core\PerformanceProfile::add('models_hydrated');
        }
        $object = $objects[$key];
        foreach ($plan['children'] as $alias => $nested) {
            // Historically root joins expose [] when absent; nested missing
            // joins are omitted. Preserve that response shape.
            $nestedKey = $row[$nested['key']] ?? null;
            if ($child && empty($nestedKey) && $nestedKey !== 0) continue;
            if (!isset($object->{$alias})) $object->{$alias} = [];
            $this->append($object->{$alias}, $row, $nested, true);
        }
    }

    private function normalize(array &$objects, array $plan): void
    {
        $objects = array_values($objects);
        foreach ($objects as $object) {
            foreach ($plan['children'] as $alias => $child) {
                if (isset($object->{$alias})) $this->normalize($object->{$alias}, $child);
            }
        }
    }
}
