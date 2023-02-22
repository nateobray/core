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

    public function select($class)
    {
        return (new Statement($this->DBConn))->select($class);
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
}