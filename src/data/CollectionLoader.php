<?php
namespace obray\data;

use obray\core\PerformanceProfile;
use obray\data\sql\OrderBy;
use obray\data\sql\Select;
use obray\data\sql\Where;

/** Per-execution collection loading; no object or result cache survives a query. */
final class CollectionLoader
{
    public function __construct(private DBConn $conn) {}

    public function load(array $parents, array $collections): void
    {
        foreach ($collections as $alias => $spec) {
            $byKey = [];
            foreach ($parents as $parent) {
                $parent->{$alias} = [];
                $key = $parent->getPrimaryKeyValue();
                if ($key !== null) $byKey[(string)$key][] = $parent;
            }
            if ($byKey === []) continue;
            $class = $spec['class'];
            $table = $class::TABLE;
            $foreignKey = $spec['foreignKey'];
            $filterSql = '';
            $filterValues = [];
            if ($spec['where'] !== []) {
                $filter = new Where($class, $spec['where']);
                $filterSql = ' AND (' . preg_replace('/^\s*WHERE\s+/i', '', trim($filter->toSQL())) . ')';
                $filterValues = $filter->values();
            }
            $order = $spec['orderBy'] ?: [$table . '.' . $class::getPrimaryKey() . ' ASC'];
            foreach (array_chunk(array_keys($byKey), $spec['batchSize']) as $keys) {
                $values = $filterValues;
                $placeholders = [];
                foreach ($keys as $index => $key) {
                    $placeholder = ':many_parent_' . $index;
                    $placeholders[] = $placeholder;
                    $values[$placeholder] = $key;
                }
                $sql = (new Select($class))->toSQL()
                    . ' FROM `' . $table . '` WHERE `' . $table . '`.`' . $foreignKey . '` IN ('
                    . implode(',', $placeholders) . ')' . $filterSql
                    . (new OrderBy($order))->toSQL();
                $rows = $this->conn->run($sql, $values, \PDO::FETCH_ASSOC)[0] ?? [];
                $groups = [];
                foreach ($rows as $row) {
                    $key = $row[$table . '_' . $foreignKey] ?? null;
                    if ($key !== null) $groups[(string)$key][] = $row;
                }
                PerformanceProfile::measure('hydration', function () use ($keys, $byKey, $groups, $class, $alias) {
                    foreach ($keys as $key) {
                        foreach ($byKey[$key] as $parent) {
                            // Hydrate independently: wrappers and child objects must not
                            // be shared between parents, aliases, or statement executions.
                            $parent->{$alias} = (new ResultHydrator())->hydrate($groups[$key] ?? [], $class, []);
                        }
                    }
                });
            }
        }
    }
}
