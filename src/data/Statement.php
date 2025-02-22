<?php
namespace obray\data;

use Exception;
use obray\core\Helpers;
use obray\data\sql\Delete;
use obray\data\sql\From;
use obray\data\sql\Insert;
use obray\data\sql\Limit;
use obray\data\sql\OrderBy;
use obray\data\sql\Select;
use obray\data\sql\Update;
use obray\data\sql\Where;
use obray\data\Statement as DataStatement;

/**
 * Statement
 * 
 * A statement is a class that is used to create and run SQL statements.  Contains utility methods for building
 * dynamic queries easily, and running common multi-query operations like runOnEmptyInsert.
 * 
 * @package obray\data
 */

class Statement
{
    private string $class;
    private string $action = 'selecting';
    private mixed $results;
    private DBConn $conn;
    private string $sql;
    
    private \obray\data\sql\Insert $insert;
    private \obray\data\sql\Update $update;
    private \obray\data\sql\Delete $delete;
    private \obray\data\sql\Select $select;
    private \obray\data\sql\From $from;
    private \obray\data\sql\Where $where;
    private \obray\data\sql\Limit $limit;
    private \obray\data\sql\OrderBy $orderBy;
    
    private bool $returnSingleRow = false;

    /**
     * __construct
     * @param DBConn $conn 
     * @return void 
     */

    public function __construct(DBConn $conn)
    {
        $this->conn = $conn;
    }

    /**
     * insert
     * 
     * Start an insert statement
     * 
     * @param mixed $instance 
     * @return $this 
     */

    public function insert(mixed $instance): Statement
    {
        $this->action = 'inserting';
        $this->insert = new Insert($instance, $this->conn);
        return $this;
    }

    /**
     * update
     * 
     * Start an update statement
     * 
     * @param mixed $instance 
     * @return $this 
     */

    public function update(mixed $instance): Statement
    {
        $this->action = 'updating';
        $this->update = new Update($instance, $this->conn);
        return $this;
    }

    /**
     * delete
     * 
     * Start a delete statement
     * 
     * @param mixed $instance 
     * @return $this 
     */

    public function delete(mixed $instance): Statement
    {
        $this->action = 'deleting';
        $this->delete = new Delete($instance, $this->conn);
        return $this;
    }
    
    /**
     * select
     * 
     * Start a select statement
     * @param string $class 
     * @return $this 
     */

    public function select(string $class, ?string $sql = null): Statement
    {
        $this->action = 'selecting';
        $this->class = $class;
        $this->select = new Select($class, $sql);
        $this->from = new From($class);
        return $this;
    }

    public function count()
    {
        return $this->run('', [], true);
    }

    /**
     * leftJoin
     * 
     * Add a left join to the statement
     * 
     * @param string $name 
     * @param string $toClass 
     * @param mixed $fromClass 
     * @param string|null $toColumn 
     * @param string|null $fromColumn 
     * @return $this 
     */

    public function leftJoin(string $name, string $toClass, mixed $fromClass=null, ?string $toColumn=null, ?string $fromColumn=null, array $conditions=[]): Statement
    {
        $this->from->leftJoin($name, $toClass, $fromClass, $toColumn, $fromColumn, $conditions);
        if(!empty($this->select)) $this->select->add($name, $toClass);
        return $this;
    }

    /**
     * join
     * 
     * Add a join to the statement
     * 
     * @param string $name 
     * @param string $toClass 
     * @param mixed $fromClass 
     * @param string|null $toColumn 
     * @param string|null $fromColumn 
     * @return $this 
     */

    public function join(string $name, string $toClass, mixed $fromClass=null, ?string $toColumn=null, ?string $fromColumn=null, array $conditions = []): Statement
    {
        $this->from->join($name, $toClass, $fromClass, $toColumn, $fromColumn, $conditions);
        if(!empty($this->select)) $this->select->add($name, $toClass);
        return $this;
    }

    /**
     * where
     * 
     * Set the where clause
     * 
     * @param array $where 
     * @return $this 
     */

    public function where(array $where): Statement
    {
        $this->where = new Where($this->class, $where);
        return $this;
    }

    /**
     * limit
     * 
     * Set the limit clause
     * 
     * @param int $rows 
     * @param int $offset 
     * @return $this 
     */

    public function limit(int $rows, int $offset=0): Statement
    {
        if($rows === 1) $this->returnSingleRow = true;
        $this->limit = new Limit($rows, $offset);
        return $this;
    }

    /**
     * orderBy
     * 
     * Set the order by clause
     * 
     * @param mixed $orderBy 
     * @return $this 
     */

    public function orderBy($orderBy): Statement
    {
        $this->orderBy = new OrderBy($orderBy);
        return $this;
    }

    /**
     * out
     * 
     * Output the SQL statement to the console
     * 
     * @return $this 
     * @throws Exception 
     */

    public function out(): Statement
    {
        Helpers::console("%s", "\n\t**** SQL STATEMENT ****\n\n", "YellowBold");
        $sql = '';
        if(!empty($this->insert)) $sql .= $this->insert->toSQL();
        if(!empty($this->update)) $sql .= $this->update->toSQL();
        if(!empty($this->delete)) $sql .= $this->delete->toSQL();
        if(!empty($this->select)) $sql .= $this->select->toSQL();
        if(!empty($this->from)) $sql .= $this->from->toSQL();
        if(!empty($this->where)) $sql .= $this->where->toSQL();
        if(!empty($this->orderBy)) $sql .= $this->orderBy->toSQL();
        if(!empty($this->limit)) $sql .= $this->limit->toSQL();
        Helpers::console($sql."\n");
        if(!empty($this->where)) Helpers::console($this->where->values());
        return $this;
    }

    /**
     * runInsertOnEmpty
     * 
     * Attempt to run the statement, if the results are empty then insert the new record.  If the statement as already
     * been run then check if empty and insert the new record if they are.
     * 
     * @param DBO $instance 
     * @return Statement 
     * @throws Exception 
     */

    public function runInsertOnEmpty(DBO $instance): Statement
    {
        // get results of the existing statement
        if(empty($this->results)) $this->results = $this->run();
        // if the results are not empty simply return the statement
        if(!empty($this->results)) return $this;
        // if the results are empty then we need to insert the new record
        $primaryKey = $instance->getPrimaryKey();
        $instance->$primaryKey = (new DataStatement($this->conn))->insert($instance)->run();
        // set the results to the new instance
        $this->results = $instance;
        // return the statement
        return $this;
    }

    /**
     * runUpdateOnExists
     * 
     * Attempt to run the statement, if the results are not empty then update the existing records.  If the statement 
     * as already been run update the existing records.
     * 
     * @param array $params 
     * @return Statement 
     * @throws Exception 
     */

    public function runUpdateOnExists(array $params = []): Statement
    {
        // no params to be updated, then nothing to do
        if(empty($params)) return $this;
        // get results of the existing statement
        if(empty($this->results)) $this->results = $this->run();
        // if the results are empty simply return the statement
        if(empty($this->results) || !is_array($this->results)) return $this;
        // if the results are not empty then we need to update the record
        forEach($this->results as $result){
            forEach($params as $key => $value){
                $result->$key = $value;
            }
            (new DataStatement($this->conn))->update($result)->run();
        }
        // return the statement
        return $this;
    }

    /**
     * getResults
     * 
     * Return the results of the statement if it's not empty otherwise return null.
     * 
     * @return mixed 
     */

    public function getResults(): mixed
    {
        if(empty($this->results)) return null;
        return $this->results;
    }

    /**
     * run
     * 
     * Run the statement by constructing SQL and submitting it to the DB and return the results.
     * 
     * Alternatively if an sql string is supplied then run that instead.
     * 
     * @param string $sql 
     * @return mixed 
     * @throws Exception 
     */

    public function run(string $sql = '', array $values = [], $count = false): mixed
    {
        // check what action we are performing and call the onBefore lifecycle method
        if($this->action === 'updating') $this->update->onBeforeUpdate($this->newQuerier());
        if($this->action === 'inserting') $this->insert->onBeforeInsert($this->newQuerier());

        // if sql is not empty, then run that instead
        if(!empty($sql)){
            return $this->conn->query($sql, $values, \PDO::FETCH_ASSOC);
        }

        // construct the sql statement
        $values = [];
        if(!empty($this->insert)){
            $sql .= $this->insert->toSQL();
            $values = $this->insert->values();
        } 
        if(!empty($this->update)){
            $sql .= $this->update->toSQL();
            $values = $this->update->values();
        } 
        if(!empty($this->delete)) $sql .= $this->delete->toSQL();
        if(!empty($this->select)) $sql .= $this->select->toSQL($count);
        if(!empty($this->from)) $sql .= $this->from->toSQL();
        if(!empty($this->where)){
            $sql .= $this->where->toSQL();
            $values = array_merge($values, $this->where->values());
        }
        if(!empty($this->orderBy)) $sql .= $this->orderBy->toSQL();
        if(!empty($this->limit)) $sql .= $this->limit->toSQL();

        // send the query to the database and get the results
        $data = $this->conn->run($sql, $values, \PDO::FETCH_ASSOC);

        if($count){
            return $data[0][0]['count'];
        }

        $results = [];
        // if we are selecting then we need to populate the results
        foreach ($data[0] as $i => $row) {

            if(empty($row)) continue;

            $objProps = [];
            forEach($row as $name => $prop){
                if(strpos($name, $this->class::TABLE.'_') !== false){
                    $objProps[substr($name, strlen($this->class::TABLE.'_'))] = $prop;
                }
            }
            
            $result = new ($this->class)(...$objProps);
            if(empty($results[$result->getPrimaryKeyValue()])){
                $results[$result->getPrimaryKeyValue()] = $result;
            }
            
            //handle joins
            forEach($this->from->getJoins() as $class => $join){
                // populate data from row into our join object
                $joinResult = $this->populateJoin($row, $join);
                // if object does not already contain the join, then add it as an empty array
                if(!isSet($results[$result->getPrimaryKeyValue()]->{$join->getName()})){
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()} = array();
                }
                // if an object with the joins primary key does not exist, then added it to the join
                if(!empty($joinResult) && empty($results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()])){
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()] = $joinResult;
                } else if (!empty($joinResult) && !empty($results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()])){
                    $originalObject = $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()];
                    $resultsObj = $joinResult;
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()] = $this->merge($originalObject, $resultsObj);
                }
            }
        }

        // remove the primary keys as keys from the results
        $this->removePrimaryKeys($results);

        $this->sql = '';
        // if return only a single row, then return the first row of our results
        if($this->returnSingleRow){
            $results = array_values($results);
            if(empty($results[0])) return [];
            return $results[0];
        } 

        // if we are updating or inserting then we need to call the onAfter lifecycle method
        if($this->action === 'updating'){
            $this->update->onAfterUpdate($this->newQuerier());
        }
        if($this->action === 'inserting'){ 
            $lastId = $this->conn->lastInsertId();
            $this->insert->onAfterInsert($this->newQuerier(), $lastId);
            return $lastId;
        }

        // return the results
        if(is_array($results)) array_values($results);
        return $results;
    }

    /**
     * merge
     * 
     * Merge two objects together.  Used to merge the results of a join.
     * 
     * @param mixed $obj1 
     * @param mixed $obj2 
     * @return mixed 
     */

    private function merge($obj1, $obj2): mixed
    {
        $merged = clone $obj1;
        forEach($obj2 as $key => $value){
            if(is_array($value)){
                $merged->{$key} = $this->mergeArray($merged->{$key}, $value);
            } else if (is_object($value)){
                $merged->{$key} = $this->merge($merged->{$key}, $value);
            }
        }
        return $merged;
    }

    /**
     * mergeArray
     * 
     * Merge two arrays together.  Used to merge the results of a join.
     * 
     * @param mixed $arr1 
     * @param mixed $arr2 
     * @return array
     */

    private function mergeArray($arr1, $arr2): array
    {
        $merged = $arr1;
        forEach($arr2 as $key => $value){
            if(array_key_exists($key, $merged)){
                $merged[$key] = $this->merge($merged[$key], $value);    
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    /**
     * populateJoin
     * 
     * Populate a join object with data from a row.
     * 
     * @param mixed $row 
     * @param mixed $join 
     * @return null|object 
     */

    private function populateJoin($row, $join): null|object
    {
        $objProps = [];
        forEach($row as $n => $prop){
            if(strpos($n, $join->getName().'_') === 0){
                $objProps[substr($n, strlen($join->getName().'_'))] = $prop;
            }
        }
        $joinResult = new ($join->getToClass())(...$objProps);
        if($joinResult->empty()){
            $joinResult = null;
            return $joinResult;
        } 
        forEach($join->joins as $j){
            $result = $this->populateJoin($row, $j);
            if(empty($result)) continue;
            if(empty($joinResult->{$j->getName()})) $joinResult->{$j->getName()} = [];
            if(empty($joinResult->{$j->getName()}[$result->getPrimaryKeyValue()])){
                $joinResult->{$j->getName()}[$result->getPrimaryKeyValue()] = $result;
            }
        }
        return $joinResult;
    }

    /**
     * removePrimaryKeys
     * 
     * Remove the primary keys from the results array.
     * 
     * @param mixed $results 
     * @return void 
     */

    private function removePrimaryKeys(mixed &$results)
    {
        if(is_array($results)) $results = array_values($results);
        forEach($results as $key => &$value){
            if(is_array($value) || is_object($value)) $this->removePrimaryKeys($value);
        }
    }

    /**
     * newQuerier
     * 
     * Create a new Querier object.
     * @return Querier 
     */
    
    private function newQuerier()
    {
        return new Querier($this->conn);
    }
}