<?php
namespace obray\data\sql;

use obray\data\DBConn;
use obray\data\DBO;
use obray\data\Table;

class Delete
{
    private $instance;
    private DBConn $DBConn;
    private array $values = [];

    public function __construct(mixed $instance, DBConn $DBConn)
    {
        if (is_array($instance)) {
            if (count($instance) !== 1) {
                throw new \InvalidArgumentException('Delete requires one model; delete collections explicitly.');
            }
            $instance = reset($instance);
        }
        if (!$instance instanceof DBO) {
            throw new \InvalidArgumentException('Delete requires a model instance.');
        }
        $this->instance = $instance;
        $this->DBConn = $DBConn;
    }

    public function toSQL()
    {
        $instance = $this->instance;
        $table = Table::getTable($instance::class);
        $primaryKey = $instance->getPrimaryKey();
        $this->values = ['pk_' . $primaryKey => WriteKey::value($instance->$primaryKey)];
        return "DELETE FROM " . $table . "\n WHERE `" . $primaryKey . '` = :pk_' . $primaryKey . "\n";
    }

    public function values(): array
    {
        return $this->values;
    }
}
