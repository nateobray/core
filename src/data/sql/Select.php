<?php
namespace obray\data\sql;

use obray\data\Table;

class Select
{
    private $classes = [];
    private $raw_sql = [];

    public function __construct(string $class, ?string $sql = null)
    {
        $this->classes[$class::TABLE] = $class;
        if(!empty($sql)) $this->raw_sql[$class::TABLE] = $sql;
    }

    public function add(string $name, string $class)
    {
        $this->classes[$name] = $class;
    }

    public function toSQL($shouldCount = false)
    {
        if($shouldCount) return "  SELECT count(*) as `count`\n";
        $columnSQL = [];
        forEach($this->classes as $name => $class){
            $columns = Table::getColumns($class);
            foreach($columns as $column){
                $columnSQL[] = "`".$name.'`.`'.$column->propertyName.'` AS `' . $name . '_' . $column->propertyName . '`';
            }
        }
        forEach($this->raw_sql as $name => $sql){
            $columnSQL[] = $sql;
        }
        return "  SELECT\n\t" . implode(",\n\t",$columnSQL) . "\n";
    }
}