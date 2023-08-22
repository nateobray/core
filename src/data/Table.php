<?php
namespace obray\data;

use obray\core\Helpers;
use obray\data\sql\Index;
use obray\data\sql\SQLForeignKey;
use ReflectionClass;

class Table
{
    private DBConn $DBConn;
    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    static public function getTable($class)
    {
        $reflection = new \ReflectionClass($class);
        try {
            $table = $class::TABLE;
        } catch (\Exception $e) {
            throw new \Exception("Class does not have a table property, not compatible with data class.");
        }
        return $table;
    }

    static public function getPrimaryKey(string $class)
    {
        $reflection = new \ReflectionClass($class);
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

    static public function getColumns($class)
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $columns = [];
        forEach($properties as $property){
            $propertyType = $property->getType();
            if($propertyType === null) continue;
            $propertyClass = $propertyType->getName();
            if(strpos($propertyClass, 'obray\\dataTypes\\') === false && strpos($property->name, 'col_') !== 0) continue;
            $property->propertyClass = $propertyClass;
            $property->propertyName = substr($property->name, 4);
            $columns[] = $property;
        }
        return $columns;
    }

    public function createAll($path = '/')
    {
        //FEATURE_SET
        //print_r(__BASE_DIR__ . 'src/models' . $path . "\n");
        $files = scandir(__BASE_DIR__ . 'src/models' . $path);
        forEach($files as $i => $file){
            if($i < 2) continue;
            //print_r("\t" . __BASE_DIR__ . 'src/models' . $path . $file . "\n");
            if(is_dir(__BASE_DIR__ . 'src/models' . $path . $file)){
                //print_r(__BASE_DIR__ . 'src/models' . $path . $file . "\n");
                $this->createAll( $path . $file . '/');
            } else {
                $modelPath = str_replace('/', '\\', $path);
                $classStr = "\models" . $modelPath . str_replace('.php', '', $file);
                if(is_subclass_of($classStr, 'obray\data\DBO')){

                    
                    $reflectionClass = new ReflectionClass($classStr);
                    $ClassFeatureSet = $reflectionClass->getConstant('FEATURE_SET');
                    if(empty($ClassFeatureSet)) continue;

                    
                    if(!empty(array_intersect(__FEATURE_SET__, $ClassFeatureSet))){
                        Helpers::console("%s", $classStr . "\n", "GreenBold");
                        $this->create($classStr);
                    }
                    
                } else {
                    //Helpers::console("%s", $classStr . "\n", "RedBold");
                }
                //$model = new $classStr();
                
            }
            
        }

    }

    public function create($class)
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $keys = [];
        $constraints = [];

        $table = self::getTable($class);

        $sql = $this->disableConstraints() . "\nCREATE TABLE `" . $table . '`' . "(\n";

        Helpers::console("%s","*** Scripting Table " . $table . " ***\n","GreenBold");
        
        $columnSQL = [];
        $columns = self::getColumns($class);
        forEach($columns as $column){
            if(strpos($column->propertyClass, 'PrimaryKey')) $primaryKey = $column->propertyName;
            $columnSQL[] = "\t" . ($column->propertyClass)::createSQL($column->propertyName);
        }
        $sql .= implode(",\n", $columnSQL);

        // build indexes
        if(defined($class . '::INDEXES')){
            forEach($class::INDEXES as $index){
                $keys[] = Index::createSQL(...$index);
            }
        }
        
        // build Foreign Keys
        if(defined($class . '::FOREIGN_KEYS')){
            forEach($class::FOREIGN_KEYS as $key){
                $foreign = SQLForeignKey::createSQL(...$key);
                $keys[] = $foreign[0];
                $constraints[] = $foreign[1];
            }
        }
        
        if(!empty($primaryKey)){
            $sql .= ",\n\n\tPRIMARY KEY (`" . $primaryKey . "`)";
        }
        if(!empty($keys)){
            $sql .= ",\n\t";
            $sql .= implode(",\n\t", $keys);
        }
        if(!empty($constraints)){
            $sql .= ",\n\t";
            $sql .= implode(",\n\t", $constraints);
        }

        $sql .= "\n".') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;' . "\n\n";

        $sql .= $this->enableConstraints();

        Helpers::console("%s","\n" . $sql . "\n","White");

        $this->DBConn->query($sql);

        if(defined($class . '::SEED_FILE')){
            $this->seedFile($class, $columns);
        }

        if(defined($class . '::SEED_CONSTANTS')){
            $this->seedConstants($class, $columns);
        }
    }

    private function seedFile($class, $columns)
    {
        $querier = new Querier($this->DBConn);

        $reflectionClass = new ReflectionClass($class);
        $SeedFile = $reflectionClass->getConstant('SEED_FILE');

        print_r(__BASE_DIR__ . 'src/seeds/' . $SeedFile . "\n");
        
        $handle = fopen(__BASE_DIR__ . 'src/seeds/' . $SeedFile, 'r');
        $count = 0; $keys = [];
        if ($handle !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                print_r($data);

                if($count === 0){
                    $keys = $data;
                    ++ $count;
                    continue;
                }

                $params = [];
                forEach($data as $index => $d){
                    $params[$keys[$index]] = $d;
                }

                $obj = new $class(...$params);
                $querier->insert($obj)->run();
            }
            fclose($handle);
        } else {
            // Handle the error
        }
        
    }

    private function seedConstants($class, $columns)
    {
        $reflection = new \ReflectionClass($class);
        $constants = $reflection->getConstants();
        $querier = new Querier($this->DBConn);
        forEach($constants as $key => $value){
            if(in_array($key, ['SEED_CONSTANTS', 'TABLE', 'FOREIGN_KEYS', 'INDEXES', 'FEATURE_SET', 'SEED_FILE'])) continue;
            $key = ucwords(strtolower(str_replace('_', ' ', $key)));
            $obj = new $class(...[
                $columns[0]->propertyName => $value,
                $columns[1]->propertyName => $key
            ]);
            $querier->insert($obj)->run();
        }
    }

    private function disableConstraints()
    {
        $sql = "
            SET @ORIG_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
            SET FOREIGN_KEY_CHECKS = 0;
            
            SET @ORIG_UNIQUE_CHECKS = @@UNIQUE_CHECKS;
            SET UNIQUE_CHECKS = 0;
            
            SET @ORIG_TIME_ZONE = @@TIME_ZONE;
            SET TIME_ZONE = '+00:00';
            
            SET @ORIG_SQL_MODE = @@SQL_MODE;
            SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
        ";
        return $sql;
    }

    private function enableConstraints()
    {
        $sql = "
            SET FOREIGN_KEY_CHECKS = @ORIG_FOREIGN_KEY_CHECKS;
            SET UNIQUE_CHECKS = @ORIG_UNIQUE_CHECKS;
            SET @ORIG_TIME_ZONE = @@TIME_ZONE;
            SET TIME_ZONE = @ORIG_TIME_ZONE;
            SET SQL_MODE = @ORIG_SQL_MODE;
        ";
        $sql;
    }
}