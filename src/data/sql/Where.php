<?php
namespace obray\data\sql;

class Where
{
    private $class;
    private $where;
    private array $values = [];

    public function __construct(string $class, $where = [])
    {
        $this->class = $class;
        $this->where = $where;
    }

    public function toSQL()
    {
        $this->values = [];
        if ($this->where === []) {
            throw new \InvalidArgumentException('WHERE requires at least one condition.');
        }
        $conditions = [];
        foreach ($this->where as $column => $value) {
            $conditions[] = $this->getExpression($column, $value);
        }
        return '  WHERE ' . implode("\n    AND ", $conditions) . "\n";
    }

    private function getExpression(string $column, $value): string
    {
        if (is_array($value) || $value instanceof AndOp) {
            $isAnd = $value instanceof AndOp;
            $members = $isAnd ? $value->getValue() : $value;
            if ($members === []) {
                if ($isAnd) throw new \InvalidArgumentException('AND requires at least one condition.');
                return '(1 = 0)';
            }
            $expressions = [];
            foreach ($members as $member) {
                $expressions[] = $this->getExpression($column, $member);
            }
            return '( ' . implode($isAnd ? ' AND ' : ' OR ', $expressions) . ' )';
        }
        if ($value instanceof RawSQL) return $value->getValue();
        if ($value === null) return $column . ' IS NULL';
        if ($value instanceof Not && $value->getValue() === null) return $column . ' IS NOT NULL';

        $operator = '=';
        foreach ([Not::class => '!=', GT::class => '>', GTE::class => '>=',
            LT::class => '<', LTE::class => '<=', Like::class => 'LIKE'] as $type => $comparison) {
            if ($value instanceof $type) {
                $operator = $comparison;
                $value = $value->getValue();
                break;
            }
        }
        // Each occurrence owns a binding, independent of aliases, column names and group depth.
        $placeholder = ':where_' . count($this->values);
        $this->values[$placeholder] = $value;
        return $column . ' ' . $operator . ' ' . $placeholder;
    }

    public function values(): array
    {
        return $this->values;
    }
}
