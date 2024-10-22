<?php
namespace obray\data;

use obray\data\Statement;

/**
 * Querier
 * 
 * A querier is a class that is used to create SQL statements
 * 
 * @package obray\data
 */

class Querier
{
    public $DBConn;

    /**
     * __construct
     * 
     * @param DBConn $DBConn 
     * @return void 
     */

    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    /**
     * insert
     * 
     * Create an insert statement
     * 
     * @param mixed $instances 
     * @return Statement 
     */

    public function insert(mixed $instances)
    {
        return (new Statement($this->DBConn))->insert($instances);
    }

    /**
     * update
     * 
     * Create an update statement
     * 
     * @param mixed $instance 
     * @param string $action 
     * @return Statement 
     */
    public function update($instance, $action='updating')
    {
        return (new Statement($this->DBConn))->update($instance);
    }

    /**
     * delete
     * 
     * Create a delete statement
     * 
     * @param mixed $instance 
     * @param string $action 
     * @return Statement 
     */

    public function delete($instance, $action='deleting')
    {
        return (new Statement($this->DBConn))->delete($instance);
    }

    /**
     * select
     * 
     * create a select statement
     * @param mixed $class 
     * @return Statement 
     */

    public function select($class, string $sql = null)
    {
        return (new Statement($this->DBConn))->select($class, $sql);
    }

    /**
     * newQuerier
     * 
     * Create a new querier
     * 
     * @return Querier 
     */

    private function newQuerier()
    {
        return new Querier($this->DBConn);
    }

    /**
     * beginTransaction
     * 
     * Begin a transaction
     * 
     * @return void 
     */

    public function beginTransaction()
    {
        $this->DBConn->beginTransaction();
    }

    /**
     * commit
     * 
     * Commit a transaction
     * 
     * @return void 
     */

    public function commit()
    {
        $this->DBConn->commit();
    }

    /**
     * rollback
     * 
     * Rollback a transaction
     * 
     * @return void 
     */

    public function rollback()
    {
        $this->DBConn->rollback();
    }

    /**
     * inTransaction
     * 
     * Check if a transaction is in progress
     * 
     * @return bool 
     */
    
    public function inTransaction()
    {
        return $this->DBConn->inTransaction();
    }
}