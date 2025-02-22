<?php
namespace obray\data;

use obray\core\Helpers;
use obray\data\sql\Index;
use obray\data\sql\SQLForeignKey;
use PDO;
use Reflection;
use ReflectionClass;

class Table
{
    private DBConn $DBConn;
    private $tableCreationCount = 0;
    private $tablesCreated = [];
    private $tables = [];

    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

     /**
     * migrate
     * This creates and seeds all needed tables for a new project.
     * 
     * TODO: Support updates to tables
     * 
     * @param bool $printTable Prints table path to console
     * @param bool $printSQL Prints table create sql to console
     * @param bool $printSeed Prints seed data to console
     * 
     * @return void
     */
    public function migrate(bool $printTable = false, bool $printSQL = false, bool $printSeeds = false, $debug = false) : void
    {

        // get a list of existing tables
        $stmt = $this->DBConn->query("SELECT * 
                                FROM INFORMATION_SCHEMA.TABLES 
                               WHERE TABLE_SCHEMA = '" . __BASE_DB_NAME__ . "';"
        );
        $this->tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->tables[] = $row['TABLE_NAME'];
        }

        // Create and seed all tables from models folder in bm project.
        $this->createTablesFromModelsFolder('/', $printTable, $printSQL, $printSeeds);

        // Manually create Tables that live in obray/src/users/
        Helpers::console("%s", "**** Tables from core ****" . "\n", "RedBackground");
        $this->create('obray\users\RolePermission', $printTable, $printSQL, $printSeeds);
        $this->create('obray\users\Role', $printTable, $printSQL, $printSeeds);
        $this->create('obray\users\UserRole', $printTable, $printSQL, $printSeeds);
        $this->create('obray\users\UserPermission', $printTable, $printSQL, $printSeeds);

        Helpers::console("%s", "\nWARNING: Currently only supports creating tables from models, WILL NOT UPDATE TABLES. \n", "YellowBold");

        Helpers::console("%s","\n ********* Tables Created (" . $this->tableCreationCount . ") **********\n", "GreenBold");
        if(empty($this->tablesCreated)){
            Helpers::console("%s","\n\t No tables created\n\n", "GreenBold");
        }
        sort($this->tablesCreated);
        forEach($this->tablesCreated as $createdTable){
            Helpers::console("%s","\t $createdTable\n", "GreenBold");
        }

        // Manually fix order of Users table
        $this->fixUserTableOrder();
    }

     /**
     * createTablesFromModelsFolder
     * This method traverses the models folder recusively in the bm project and creates tables for any classes that extend the DBO Class.
     * It compares against the Feature Set that has been configured for the project.
     * 
     * @param string $path
     * @param bool $printTable Prints table path to console
     * @param bool $printSQL Prints table create sql to console
     * @param bool $printSeed Prints seed data to console
     * 
     * @return void
     */
    private function createTablesFromModelsFolder(string $path = '/', bool $printTable = true, bool $printSQL = true, bool $printSeeds = true) : void
    {
        $files = scandir(__BASE_DIR__ . 'src/models' . $path);
        forEach($files as $i => $file){
            if($i < 2) continue;
            if(is_dir(__BASE_DIR__ . 'src/models' . $path . $file)){
                $this->createTablesFromModelsFolder( $path . $file . '/', $printTable, $printSQL, $printSeeds);
            } else {
                $modelPath = str_replace('/', '\\', $path);
                $classStr = "\models" . $modelPath . str_replace('.php', '', $file);
                // Check if it is a DBO class
                if(is_subclass_of($classStr, 'obray\data\DBO')){
                    $reflectionClass = new ReflectionClass($classStr);
                    $ClassFeatureSet = $reflectionClass->getConstant('FEATURE_SET');
                    if(empty($ClassFeatureSet)) continue;
                    
                    // Check against Feature Set
                    if(!empty(array_intersect(__FEATURE_SET__, $ClassFeatureSet))){
                        // Helpers::console("%s", $classStr . "\n", "GreenBold");
                        $this->create($classStr, $printTable, $printSQL, $printSeeds);
                    }
                } 
            }
        }
    }

    /**
     * create
     * This creates and seeds a table for the given class that is provided.
     * 
     * @param string $class
     * @param bool $printTable Prints table path to console
     * @param bool $printSQL Prints table create sql to console
     * @param bool $printSeed Prints seed data to console
     * 
     * @return void
     */
    public function create(string $class, bool $printTable = true, bool $printSQL = true, bool $printSeeds = true) : void
    {
        $this->disableConstraints();

        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $keys = [];
        $constraints = [];

        $table = self::getTable($class);
        

        $columnSQL = [];
        $columns = self::getColumns($class);

        if(in_array($class::TABLE, $this->tables)){
            Helpers::console("%s", $class::TABLE . " already exists\n", "RedBold");

            $this->updateTable($class::TABLE, $class);
            
            if(defined($class . '::KEEP_SEEDS_CURRENT') && defined($class . '::SEED_FILE')){
                $this->seedFile($class, $printSeeds);
            }
    
            if(defined($class . '::KEEP_SEEDS_CURRENT') && defined($class . '::SEED_CONSTANTS')){
                $this->seedConstants($class, $columns, $printSeeds);
            }

            return;
        }

        $sql = "\nCREATE TABLE `" . $table . '`' . "(\n";
        $this->tableCreationCount++;
        array_push($this->tablesCreated, $table);

        if ($printTable) Helpers::console("%s","*** Scripting Table " . $table . " ***\n","GreenBold");

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

        if($printSQL) Helpers::console("%s","\n" . $sql . "\n","Blue");

        $this->DBConn->query($sql);

        if(defined($class . '::SEED_FILE')){
            $this->seedFile($class, $printSeeds);
        }

        if(defined($class . '::SEED_CONSTANTS')){
            $this->seedConstants($class, $columns, $printSeeds);
        }

        $this->enableConstraints();
    }

    private function updateTable($table, $class)
    {
        //print_r($class . "\n");
        $classCols = $this->getColumns($class);
        
        $stmt = $this->DBConn->query("SHOW CREATE TABLE $table;");
        $create = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            //print_r($row["Create Table"]);
            
            // Use regex to capture column details
            //$pattern = '/\s*`(\w+)`\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?\s*(unsigned)?\s*(NOT NULL)?\s*(AUTO_INCREMENT)?\s*(DEFAULT\s*(?:NULL|CURRENT_TIMESTAMP|\'[^\']*\'))?(?:\s*ON UPDATE CURRENT_TIMESTAMP)?,?/i';
            //$pattern = '/\s*`(\w+)`\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?\s*(unsigned)?\s*(?:COLLATE\s+\w+)?\s*(NOT NULL)?\s*(AUTO_INCREMENT)?\s*(DEFAULT\s*(?:NULL|CURRENT_TIMESTAMP|\'[^\']*\'))?(?:\s*ON UPDATE CURRENT_TIMESTAMP)?,?/i';
            $pattern = '/\s*`(\w+)`\s+(\w+)(?:\((\d+)(?:,\s*(\d+))?\))?\s*(unsigned)?\s*(?:CHARACTER SET\s+\w+)?\s*(?:COLLATE\s+\w+)?\s*(NOT NULL)?\s*(AUTO_INCREMENT)?\s*(DEFAULT\s*(?:NULL|CURRENT_TIMESTAMP|\'[^\']*\'))?(?:\s*ON UPDATE CURRENT_TIMESTAMP)?,?/i';

            $primaryKeyPattern = '/PRIMARY KEY \(`([^`]+)`\)/';

            preg_match_all($pattern, $row["Create Table"], $matches, PREG_SET_ORDER);
            preg_match($primaryKeyPattern, $row["Create Table"], $primaryKeyMatch);

            $primaryKey = isset($primaryKeyMatch[1]) ? $primaryKeyMatch[1] : null;

            $columns = [];
            foreach ($matches as $match) {
                if (!empty($match[2]) && $match[2] == 'FOREIGN') continue;
                $column = [
                    'name' => $match[1],
                    'type' => $match[2],
                    'length' => !empty($match[3]) ? $match[3] : ($match[2] == 'int' ? 11 : null),
                    'precision' => ($match[2] == 'decimal' && !empty($match[4])) ? $match[4] : null,
                    'unsigned' => !empty($match[5]) ? true : false,
                    'not_null' => !empty($match[6]) ? true : false,
                    'auto_increment' => !empty($match[7]) ? true : false,
                    'default' => !empty($match[8]) ? $match[8] : null,
                    'on_update' => strpos($match[0], 'ON UPDATE CURRENT_TIMESTAMP') !== false ? 'CURRENT_TIMESTAMP' : null,
                    'is_primary' => $match[1] === $primaryKey, // Mark if this column is the primary key
                ];
                $columns[] = $column;
            }

            $newCols = $classCols;
            foreach ($classCols as $index => $classCol) {
                foreach ($columns as $column) {
                    if ($classCol->name == 'col_' . $column['name']) {
                        unset($newCols[$index]);
                    }
                }
            }

            if(!empty($newCols)){
                forEach($newCols as $newCol){

                    Helpers::console("%s", "\nTable ");
                    Helpers::console("%s", $table, "YellowBold");
                    Helpers::console("%s", " is missing column ");
                    Helpers::console("%s", str_replace('col_', '', $newCol->name) . ": \n\n", "YellowBold");
                    
                    $this->addColumn($table, ($newCol->propertyClass)::createSQL(str_replace('col_', '', $newCol->name)));
                }
            }
            
            forEach($columns as $column){
                
                $type = $this->getType($column);
                
                $isColumnFound = false; $selectedClassCol = null;
                forEach($classCols as $classCol){

                    if($classCol->propertyClass == 'obray\data\types\ForeignKeyNullable') $classCol->propertyClass = 'obray\data\types\Int11UnsignedNullable';
                    if($classCol->propertyClass == 'obray\data\types\ForeignKey') $classCol->propertyClass = 'obray\data\types\Int11Unsigned';
                    if($classCol->propertyClass == 'obray\data\types\Password') $classCol->propertyClass = 'obray\data\types\Varchar255Nullable';
                    if($classCol->name == 'col_' . $column['name']) {
                        
                        $selectedClassCol = $classCol;
                        $isColumnFound = true;
                        break;
                    }         
                }
                
                if(($selectedClassCol && $isColumnFound && 'obray\\data\\types\\' . $type != $selectedClassCol->propertyClass)) {

                    //print_r(('obray\\data\\types\\' . $type)::createSQL($column['name']) . "\n");
                    //print_r($column);
                    //print_r($selectedClassCol);

                    Helpers::console("%s", "\nOn table ");
                    Helpers::console("%s", $table, "YellowBold");
                    Helpers::console("%s", " on column ");
                    Helpers::console("%s", str_replace('col_', '', $selectedClassCol->name) . ": \n\n", "YellowBold");
                    Helpers::console("%s", "\tCurrent Data Type: ");
                    Helpers::console("%s", 'obray\\data\\types\\' . $type . "\n", "Blue");
                    Helpers::console("%s", "\tDefined Data Type: ");
                    Helpers::console("%s", $selectedClassCol->propertyClass . "\n\n", "Blue");

                    $this->alterTable($table, ($selectedClassCol->propertyClass)::createSQL(str_replace('col_', '', $selectedClassCol->name)));
                };
                
                //$columns[] = $column;
            }

            $pattern = '/CONSTRAINT\s+`([^`]+)`\s+FOREIGN KEY\s+\(`([^`]+)`\)\s+REFERENCES\s+`([^`]+)`\s+\(`([^`]+)`\)(?:\s+ON DELETE\s+(CASCADE|SET NULL|RESTRICT|NO ACTION))?(?:\s+ON UPDATE\s+(CASCADE|SET NULL|RESTRICT|NO ACTION))?/';

            preg_match_all($pattern, $row["Create Table"], $matches, PREG_SET_ORDER);

            $foreignKeys = [];
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'constraint_name' => $match[1],
                    'column_name' => $match[2],
                    'referenced_table' => $match[3],
                    'referenced_column' => $match[4],
                    'on_delete' => $match[5] ?? null,
                    'on_update' => $match[6] ?? null,
                ];
            }

            // build Foreign Keys

            if(defined($class . '::FOREIGN_KEYS')){
                $newForeignKeys = $class::FOREIGN_KEYS;
                forEach($class::FOREIGN_KEYS as $index => $key){
                    forEach($foreignKeys as $foreignKey){
                        if($foreignKey['column_name'] == $key[0] && $foreignKey['referenced_table'] == $key[1] && $foreignKey['referenced_column'] == $key[2]){
                            unset($newForeignKeys[$index]);
                        }
                    }
                }
            }

            if(!empty($newForeignKeys)) {

                foreach($newForeignKeys as $newForeignKey){

                    Helpers::console("%s", "\nTable ");
                    Helpers::console("%s", $table, "YellowBold");
                    Helpers::console("%s", " is missing a foreign key to ");
                    helpers::console("%s", $newForeignKey[1], "YellowBold");
                    Helpers::console("%s", " on ");
                    helpers::console("%s", $newForeignKey[0], "YellowBold");
                    helpers::console("%s", ' -> ');
                    helpers::console("%s", $newForeignKey[2] . "\n\n", "YellowBold");

                    $foreign = SQLForeignKey::createSQL(...$newForeignKey);
                    $this->addForeignKey($table, $foreign[1]);
                }
            }

            //print_r($columns);
        }
        
        
    }

    private function addColumn($table, $sql)
    {
        $alterSql = "ALTER TABLE $table ADD COLUMN $sql;";

        Helpers::console("%s", "*** SQL TO EXECUTE START ***\n\n", "WhiteBold");
        Helpers::console("%s", "\t" . $alterSql . "\n\n");
        Helpers::console("%s", "*** SQL TO EXECUTE END ***\n\n\n", "WhiteBold");
        // Prompt the user for confirmation
        echo "Are you sure you want to make changes? (y/n): ";
        $userInput = trim(fgets(STDIN)); // Read user input from the standard input

        // Check if the user input is "y" or "Y"
        if (strtolower($userInput) === 'y') {
            echo "Proceeding with changes...\n";
            $this->DBConn->run($alterSql);
        } else {
            echo "Operation cancelled.\n";
        }

    }

    private function addForeignKey($table, $sql = '')
    {
        $alterSql = "ALTER TABLE $table ADD $sql;";
        Helpers::console("%s", "*** SQL TO EXECUTE START ***\n\n", "WhiteBold");
        Helpers::console("%s", "\t" . $alterSql . "\n\n");
        Helpers::console("%s", "*** SQL TO EXECUTE END ***\n\n\n", "WhiteBold");
        // Prompt the user for confirmation
        echo "Are you sure you want to make changes? (y/n): ";
        $userInput = trim(fgets(STDIN)); // Read user input from the standard input

        // Check if the user input is "y" or "Y"
        if (strtolower($userInput) === 'y') {
            echo "Proceeding with changes...\n";
            $this->DBConn->run($alterSql);
        } else {
            echo "Operation cancelled.\n";
        }
    }

    private function alterTable($table, $sql)
    {
        $alterSql = "ALTER TABLE $table MODIFY COLUMN $sql;";
        Helpers::console("%s", "*** SQL TO EXECUTE START ***\n\n", "WhiteBold");
        Helpers::console("%s", "\t" . $alterSql . "\n\n");
        Helpers::console("%s", "*** SQL TO EXECUTE END ***\n\n\n", "WhiteBold");
        // Prompt the user for confirmation
        echo "Are you sure you want to make changes? (y/n): ";
        $userInput = trim(fgets(STDIN)); // Read user input from the standard input

        // Check if the user input is "y" or "Y"
        if (strtolower($userInput) === 'y') {
            echo "Proceeding with changes...\n";
            $this->DBConn->run($alterSql);
        } else {
            echo "Operation cancelled.\n";
        }

    }

    private function getType($column)
    {
        switch($column['type'])
        {
            case 'datetime':
                if($column['default'] == 'DEFAULT CURRENT_TIMESTAMP') return 'DateTimeCreated';
                if($column['on_update'] == 'CURRENT_TIMESTAMP') return 'DateTimeModified';
                if($column['default'] == 'DEFAULT NULL') return 'DateTimeNullable';
                return 'DateTime';
                break;
            case 'date':
                if($column['default'] == 'DEFAULT NULL') return 'DateNullable';
                return 'Date';
                break;
            case 'varchar':
                if($column['not_null']) return 'Varchar' . $column['length'];
                return 'Varchar' . $column['length'] . 'Nullable';
                break;
            case 'int':
                if($column['is_primary'] == true) return 'PrimaryKey';
                $type = 'Int' . $column['length'];
                if($column['unsigned']) $type .= 'Unsigned';
                if(!$column['not_null']) $type .= 'Nullable';
                if($column['default'] == 'DEFAULT \'1\'') $type .= 'Default1';
                if($column['default'] == 'DEFAULT \'0\'') $type .= 'Default0';
                return $type;
                break;
            case 'tinyint':
                if($column['default'] == 'DEFAULT \'0\'') return 'Boolean';
                return 'BooleanTrue';
            case 'text':
                return 'Text';
            case 'decimal':
                $type = 'Decimal';
                if (!empty($column['precision']) && $column['precision'] != 2) {
                    $type .= $column['precision'];
                }
                if (!$column['not_null']) $type .= 'Nullable';
                return $type;
                break;
            case 'float':
                $type = 'Flt';
                if($column['unsigned']) $type .= 'Unsigned';
                if(!$column['not_null']) $type .= 'Nullable';
                return 'Flt';
                break;
        }
    }

    

    /**
     * getTable
     * This finds the Table name for a given DBO class
     * 
     * @param string $class
     * @return string 
     * @throws Exception
     */
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

    /**
     * getPrimaryKey
     * This finds the primary key for a given DBO class
     * 
     * @param string $class
     * @return string 
     * @throws Exception
     */
    static public function getPrimaryKey(string $class) : string
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

    /**
     * getColumns
     * Parses a given class properties and generates an array of column names from those properties
     * Properties are defined with a 'col_' at the beginning of them.
     * 
     * @param string $class
     * @return array $columns
     */
    static public function getColumns(string $class) : array
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $columns = [];
        forEach($properties as $property){
            $propertyType = $property->getType();
            if($propertyType === null) continue;
            $propertyClass = $propertyType->getName();
            if(strpos($propertyClass, 'obray\\dataTypes\\') === false && strpos($property->name, 'col_') !== 0) continue;
            
            $columns[] = (object)[
                'name' => $property->name,
                'class' => $property->class,
                'propertyClass' => $propertyClass,
                'propertyName' => substr($property->name, 4)
            ];
        }
        return $columns;
    }

    /**
     * seedFile
     * This seeds a table from a given .csv file found in /src/seeds/ folder
     * 
     * @param string $class
     * @param bool $printSeed Prints seed data to console
     * 
     * @return void
     */
    private function seedFile(string $class, bool $printSeed = true) : void
    {
        $querier = new Querier($this->DBConn);

        $reflectionClass = new ReflectionClass($class);
        $SeedFile = $reflectionClass->getConstant('SEED_FILE');

        if($printSeed) Helpers::console("%s",'Seed File: ' . $SeedFile . "\n", "Purple");

        $results = $querier->select($class)->run();
        $resultHashTable = [];
        forEach($results as $result){
            $resultHashTable[$result->{$result->getPrimaryKey()}] = hash('sha256', implode('||||', $result->toArray()));
        }
        
        $handle = fopen(__BASE_DIR__ . 'src/seeds/' . $SeedFile, 'r');
        $count = 0; $keys = [];
        if ($handle !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                // skip first row for csv headers
                if($count === 0){
                    $keys = $data;
                    ++ $count;
                    continue;
                }

                if ($printSeed) print_r($data);

                $params = [];
                forEach($data as $index => $d){
                    $params[$keys[$index]] = $d;
                }

                $obj = new $class(...$params);        
                if(empty($resultHashTable[$params[$obj->getPrimaryKey()]])){
                    $querier->insert($obj)->run();
                    Helpers::console("%s", "Adding new seed in $class\n", "Purple");
                }

                if(!empty($resultHashTable[$params[$obj->getPrimaryKey()]]) && $resultHashTable[$params[$obj->getPrimaryKey()]] !== hash('sha256', implode('||||', $params))) {
                    $querier->update($obj)->run();
                    Helpers::console("%s", "Updating seed in $class\n", "Purple");
                }

                //Helpers::console
            }
            fclose($handle);
        } else {
            Helpers::console("%s", $class . "\n", "RedBackground");
        }
    }

    /**
     * seedConstants
     * This seeds a table from the constants that are properties of that given class
     * 
     * @param string $class
     * @param array $columns
     * @param bool $printSeed Prints seed data to console
     * 
     * @return void
     */
    private function seedConstants(string $class, array $columns, bool $printSeed) : void
    {
        
        if($printSeed) Helpers::console("%s", 'Seeding Constants from : ' . $class . "\n", "Purple");
        $reflection = new \ReflectionClass($class);
        $constants = $reflection->getConstants();
        $querier = new Querier($this->DBConn);

        $results = $querier->select($class)->run();
        $resultHashTable = [];
        forEach($results as $result){
            $resultHashTable[$result->{$result->getPrimaryKey()}] = hash('sha256', implode('||||', $result->toArray()));
        }
        
        forEach($constants as $key => $value){

            
            if(in_array($key, ['SEED_CONSTANTS', 'TABLE', 'FOREIGN_KEYS', 'INDEXES', 'FEATURE_SET', 'SEED_FILE', 'KEEP_SEEDS_CURRENT'])) continue;
            $key = ucwords(strtolower(str_replace('_', ' ', $key)));

            if(empty($resultHashTable[$value])){
                $obj = new $class(...[
                    $columns[0]->propertyName => $value,
                    $columns[1]->propertyName => $key
                ]);
                Helpers::console("%s", "Adding new seed: " . $key . ": " .$value . "\n", "Purple");
                $querier->insert($obj)->run();
            }

            if(!empty($resultHashTable[$value]) && $resultHashTable[$value] !== hash('sha256', implode('||||', [$columns[0]->propertyName => $value, $columns[1]->propertyName => $key]))) {
                
                $obj = new $class(...[
                    $columns[0]->propertyName => $value,
                    $columns[1]->propertyName => $key
                ]);
                
                Helpers::console("%s", "Updating seed: " . $key . ": " .$value . "\n", "Purple");
                $querier->update($obj)->run();
            }

            
        }
    }

    /**
     * disableConstraints
     * SQL needed to disable constraints
     * 
     * @return void
     */
    private function disableConstraints() : void
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
        $this->DBConn->query($sql);
    }

    /**
     * enableConstraints
     * SQL needed to enable constraints
     * 
     * @return void
     */
    private function enableConstraints() : void
    {
        $sql = "
            SET FOREIGN_KEY_CHECKS = @ORIG_FOREIGN_KEY_CHECKS;
            SET UNIQUE_CHECKS = @ORIG_UNIQUE_CHECKS;
            SET @ORIG_TIME_ZONE = @@TIME_ZONE;
            SET TIME_ZONE = @ORIG_TIME_ZONE;
            SET SQL_MODE = @ORIG_SQL_MODE;
        ";
        $this->DBConn->query($sql);
    }

    /**
     * fixUserTableOrder
     * This fixes the order of the columns. Right now based on how this particular table is created the entity_id ends up as the first column. 
     * 
     * @return void
     */
    public function fixUserTableOrder() : void
    {
        $sql = "
            ALTER TABLE `Users`
            CHANGE `user_id` `user_id` int(11) unsigned NOT NULL auto_increment FIRST,
            CHANGE `user_first_name` `user_first_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER `user_id`,
            CHANGE `user_last_name` `user_last_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER `user_first_name`,
            CHANGE `user_email` `user_email` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL AFTER `user_last_name`,
            CHANGE `user_password` `user_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER `user_email`,
            CHANGE `user_is_active` `user_is_active` tinyint(1) NOT NULL DEFAULT '1' AFTER `user_password`,
            CHANGE `user_is_system` `user_is_system` tinyint(1) NOT NULL DEFAULT '0' AFTER `user_is_active`,
            CHANGE `user_failed_attempts` `user_failed_attempts` int(11) unsigned NOT NULL DEFAULT '0' AFTER `user_is_system`,
            CHANGE `user_last_login` `user_last_login` datetime NULL AFTER `user_failed_attempts`,
            CHANGE `entity_id` `entity_id` int(11) unsigned NULL AFTER `user_last_login`,
            CHANGE `user_token` `user_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER `entity_id`,
            CHANGE `user_pin` `user_pin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL AFTER `user_token`;
        ";

        $this->DBConn->query($sql);
    }
}
