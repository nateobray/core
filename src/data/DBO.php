<?php
namespace obray\data;

use JsonSerializable;
use obray\data\types\BaseType;
use obray\data\types\Password;
use ReflectionProperty;

#[\AllowDynamicProperties]
class DBO implements JsonSerializable
{
    private $primaryKeyValue;
    private $primaryKeyColumn;
    private array $encodeNot = [];
    protected bool $_is_dirty = false;

    public function __construct(...$params)
    {
        $columns = Table::getColumns(static::class);
        forEach($columns as $index => $column){
            // if neither property exists get default value
            if(!array_key_exists($column->propertyName, $params) && !array_key_exists($index, $params)) {
                $reflection = new \ReflectionClass($column->propertyClass);
                $value = $reflection->getConstant('DEFAULT');
            } 
            // if named assoc array was passed get that way
            if(array_key_exists($column->propertyName, $params)) $value = $params[$column->propertyName];    
            // if numerically indexed array get value that way
            if(array_key_exists($index, $params)) $value = $params[$index];
            $this->{$column->name} = new ($column->propertyClass)($value);
            if(strpos($column->propertyClass, 'PrimaryKey') !== false){
                $this->primaryKeyColumn = $column->propertyName;
                $this->primaryKeyValue = $value;
            }
        }
        $this->markClean();
    }

    public function setEncodeNot(array $encode_not)
    {
        $this->encodeNot = $encode_not;
    }

    public function getPrimaryKeyValue()
    {
        return $this->primaryKeyValue;
    }

    static public function getPrimaryKey()
    {
        $reflection = new \ReflectionClass(static::class);
        $properties = $reflection->getProperties();
        forEach($properties as $property){
            $propertyType = $property->getType();
            if($propertyType === null) continue;
            $propertyClass = $propertyType->getName();
            if(strpos($propertyClass, 'PrimaryKey') !== false){
                return substr($property->name, 4);
            }
        }
        throw new \Exception("No primary key found.");
    }

    public function __set($key, $value)
    {
        $reflection = new \ReflectionClass(static::class);
        try {
            $property = $reflection->getProperty('col_' . $key);

            $propertyType = $property->getType();
            if($propertyType === null) throw new \Exception("Invalid property: " . $key . "\n");
            $propertyClass = $propertyType->getName();

            // Only mark dirty if the value is actually changing
            if (!isset($this->{'col_' . $key}) || $this->{'col_' . $key}->getValue() !== $value) {
                $this->markDirty();
            }

            $this->{'col_' . $key} = new $propertyClass($value);
        } catch (\Exception $e) {
            // Fall back to dynamic properties
            if (!isset($this->{$key}) || $this->{$key} !== $value) {
                $this->markDirty();
            }
            $this->{$key} = $value;
        }
    }

    public function &__get($key)
    {
        if(isSet($this->{'col_' . $key})){
            $value = $this->{'col_' . $key}->getValue();
            return $value;
        } 
        if(isSet($this->{'cust_' . $key})){
            $value = $this->{'cust_' . $key};
            return $value;
        }
        $value = &$this->{$key};
        return $value;
    }

    public function __isSet($key)
    {
        try{
            $reflection = new \ReflectionClass(static::class);
            $property = $reflection->getProperty('col_' . $key);
        } catch (\Exception $e){
            return isSet($this->{$key});
        }
        return isset($this->{'col_' . $key});
    }

    public function onBeforeInsert(Querier $querier)
    {
        return;
    }

    public function onAfterInsert(Querier $querier, int $lastId)
    {
        return;
    }

    public function onBeforeUpdate(Querier $querier)
    {
        return;
    }

    public function onAfterUpdate(Querier $querier)
    {
        return;
    }

    public function jsonSerialize(): mixed
    {
        $obj = new \stdClass();
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        
        forEach($this as $key => $prop){
            if(!empty($this->encodeNot) && in_array($key, $this->encodeNot)) continue;

            if(strpos($key, 'col_') !== false && $prop instanceof BaseType) {
                if($prop instanceof Password) continue;
                $obj->{str_replace('col_', '', $key)} = $prop->getValue();
            }

            if(strpos($key, 'cust_') !== false) {
                $obj->{str_replace('cust_', '', $key)} = $this->{$key};
            }
        }
        return $obj;
    }

    public function toArray(): array
    {
        $obj = [];
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        
        forEach($this as $key => $prop){
            if(!empty($this->encodeNot) && in_array($key, $this->encodeNot)) continue;

            if(strpos($key, 'col_') !== false && $prop instanceof BaseType) {
                if($prop instanceof Password) continue;
                $obj[str_replace('col_', '', $key)] = $prop->getValue();
            }

            if(strpos($key, 'cust_') !== false) {
                $obj[str_replace('cust_', '', $key)] = $this->{$key};
            }
        }
        return $obj;
    }

    public function empty()
    {
        return empty($this->primaryKeyValue) && $this->primaryKeyValue !== 0;
    }

    public function isDirty(): bool
    {
        return $this->_is_dirty;
    }

    public function markClean(): void
    {
        $this->_is_dirty = false;
    }

    public function markDirty(): void
    {
        $this->_is_dirty = true;
    }

}