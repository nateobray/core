<?php
namespace obray\data\sql;

class Join
{
    private $name;
    private $fromAlias;
    private $fromClass;
    private $fromColumn;
    private $toClass;
    private $toColumn;
    private $type;
    private $conditions = [];
    public $joins = [];

    const INNER = 1;
    const LEFT = 2;
    
    public function __construct($name, $fromClass, $fromColumn, $toClass, $toColumn, $conditions = [], $type = self::LEFT)
    {
        $this->name = $name;
        $this->fromClass = $fromClass;
        $this->fromColumn = $fromColumn;
        $this->toClass = $toClass;
        $this->toColumn = $toColumn;
        $this->type = $type;
        $this->conditions = $conditions;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFromTable()
    {
        return $this->fromClass::TABLE;
    }

    public function getToTable()
    {
        return $this->toClass::TABLE;
    }

    public function getToClass()
    {
        return $this->toClass;
    }

    public function getFromColumn()
    {
        return $this->fromColumn;
    }

    public function getToColumn()
    {
        return $this->toColumn;
    }

    public function addFromAlias($alias)
    {
        $this->fromAlias = $alias;
    }

    public function toSQL()
    {
        $fromTable = (empty($this->fromAlias)?$this->getFromTable():$this->fromAlias);
        $type = 'JOIN';
        if($this->type === self::LEFT){
            $type = 'LEFT JOIN';
        }
        $sql = '   ' . $type . ' `' . $this->getToTable() . '` `'.$this->name.'` ON `'. $this->name . '`.`' . $this->getToColumn() . '` = `' . $fromTable . '`.`' . $this->getFromColumn() . "`\n";
        forEach($this->conditions as $column => $value){
            if(is_array($value)){
                    $sql .= "\t\tAND `" . $this->name . '`.' . $column . " IN (" . implode(',',$value) . ")\n";                
            } else {
                if($value instanceof Not){
                    if($value->getValue() === null){
                       $sql .= "\t\tAND " . $column . ' IS NOT NULL' . "\n";
                    } else {
                        $sql .= "\t\tAND " . $column . ' != ' . $value->getValue() . "\n";
                    }
                } else if ($value instanceof GT){
                    $sql .= "\t\tAND " . $column . ' > ' . $value->getValue();
                } else if ($value instanceof GTE){
                    $sql .= "\t\tAND " . $column . ' >= ' . $value->getValue();
                } else if ($value instanceof LT){
                    $sql .= "\t\tAND " . $column . ' < ' . $value->getValue();
                } else if ($value instanceof LTE){
                    $sql .= "\t\tAND " . $column . ' <= ' . $value->getValue();
                } else if($value instanceof LIKE){
                    $sql .= "\t\tAND " . $column . ' LIKE ' . $value->getValue();
                } else if ($value instanceof RawSQL){
                    
                    $sql .= "\t\tAND " . $value->getValue();
                } else {
                    $sql .= "\t\tAND " . $column . " = " . $value . "\n";
                }
            }
        }
        forEach($this->joins as $join){
            $sql .= $join->toSQL();
        }
        return $sql;
    }
}
