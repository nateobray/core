<?php
namespace obray\data\sql;

use obray\data\DBConn;
use obray\data\Querier;
use obray\data\Table;

class Update
{
    private $instance;
    private DBConn $DBConn;
    private $values = [];
    private array $preservedColumns = [];

    public function __construct(mixed $instance, DBConn $DBConn)
    {
        $this->instance = $instance;
        $this->DBConn = $DBConn;
    }

    /** Exclude designated columns from this update, including values changed after the seed read. */
    public function preserveColumns(array $columns): void
    {
        $known = [];
        foreach (Table::getColumns($this->instance::class) as $column) {
            $known[$column->propertyName] = $column;
        }
        foreach ($columns as $name) {
            if (!is_string($name) || !isset($known[$name])
                || strpos($known[$name]->propertyClass, 'PrimaryKey') !== false) {
                throw new \InvalidArgumentException('Preserved update columns must be declared non-primary-key columns.');
            }
        }
        $this->preservedColumns = array_fill_keys($columns, true);
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function onBeforeUpdate(Querier $querier)
    {
        $this->instance->onBeforeUpdate($querier);
    }

    public function onAfterUpdate(Querier $querier)
    {
        $this->instance->onAfterUpdate($querier);
    }

    public function toSQL()
    {
        $this->values = [];
        $table = Table::getTable($this->instance::class);
        $columns = Table::getColumns($this->instance::class);

        $columnSQL = []; $whereSQL = [];
        forEach($columns as $column){
            if(strpos($column->propertyClass, 'PrimaryKey') ){
                if($this->instance->{$column->name}->empty() && $this->instance->{$column->name}->getValue() !== 0) continue;
                $whereSQL[] = "`" . $column->propertyName . "` = " . $this->instance->{$column->name}->insertSQL($this->DBConn);
                continue;
            }
            if (isset($this->preservedColumns[$column->propertyName])) continue;
            if(strpos($column->propertyClass, 'DateTimeCreated')) continue;
            if(strpos($column->propertyClass, 'DateTimeModified')) continue;
            $columnSQL[] = "`" . $column->propertyName . "` = :" . $column->name;
            $this->values[$column->name] = $this->instance->{$column->name}->getValue();
        }

        if (empty($columnSQL)) {
            throw new \LogicException('Update has no writable columns.');
        }

        $sql = "UPDATE ";
        $sql .= $table . "\n   SET "; 
        $sql .= implode(",\n", $columnSQL);

        if(!empty($whereSQL)){
            $sql .= "\n WHERE " . implode("\n  AND ", $whereSQL);
        } else {
            throw new \Exception("WHERE is empty on update (NOT ALLOWED): " . $sql);
        }
        return $sql;
    }

    public function values()
    {
        return $this->values;
    }
}