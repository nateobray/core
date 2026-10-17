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
    private array $many = [];

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

    /** Preserve selected fields without writing stale snapshot values back to the database. */
    public function preserveUpdateColumns(array $columns): Statement
    {
        if ($this->update === null) {
            throw new \LogicException('preserveUpdateColumns requires an update statement.');
        }
        $this->update->preserveColumns($columns);
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
        $this->many = [];
        return $this;
    }

    /** Load a root model's collection separately, keyed by its primary key. */
    public function withMany(string $name, string $class, string $foreignKey, array $where = [], array $orderBy = [], int $batchSize = 500): Statement
    {
        if ($this->action !== 'selecting' || !isset($this->select)) {
            throw new \LogicException('withMany requires a select statement.');
        }
        if (!preg_match('/^cust_[A-Za-z0-9_]+$/D', $name) || property_exists($this->class, $name) || isset($this->many[$name])) {
            throw new \InvalidArgumentException('A collection requires a unique cust_ alias.');
        }
        if (!is_subclass_of($class, DBO::class) || $batchSize < 1 || $batchSize > 1000) {
            throw new \InvalidArgumentException('Invalid collection model or batch size (1-1000).');
        }
        $columns = array_column(Table::getColumns($class), 'propertyName');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $foreignKey) || !in_array($foreignKey, $columns, true)) {
            throw new \InvalidArgumentException('Collection foreign key must be a model column.');
        }
        $class::getPrimaryKey();
        $this->many[$name] = compact('class', 'foreignKey', 'where', 'orderBy', 'batchSize');
        return $this;
    }

    public function count()
    {
        return $this->run('', [], true);
    }

    public function first(): mixed
    {
        $statement = clone $this;
        return $statement->limit(1)->run();
    }

    public function firstOrNull(): mixed
    {
        $result = $this->first();
        if ($result === [] || $result === null) {
            return null;
        }
        return $result;
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
        $this->returnSingleRow = ($rows === 1);
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
        if ($this->many !== []) {
            if ($this->action !== 'selecting' || $sql !== '') {
                throw new \LogicException('withMany only supports model selects.');
            }
            foreach ($this->from->getJoinMap() as $join) {
                if (isset($this->many[$join->getName()])) {
                    throw new \InvalidArgumentException('Collection alias conflicts with a join.');
                }
            }
        }
        // check what action we are performing and call the onBefore lifecycle method
        if($this->action === 'updating') {
            if (method_exists($this->update->getInstance(), 'isDirty') && !$this->update->getInstance()->isDirty()) {
                // Skip update if not dirty
                return [];
            }
            $this->update->onBeforeUpdate($this->newQuerier());
        }
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
        if(!empty($this->delete)) {
            $sql .= $this->delete->toSQL();
            $values = $this->delete->values();
        }
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
        if ($this->action === 'selecting') {
            $results = \obray\core\PerformanceProfile::measure('hydration', fn() =>
                (new ResultHydrator())->hydrate($data[0], $this->class, $this->from->getJoins()));
            if ($results !== [] && $this->many !== []) {
                (new CollectionLoader($this->conn))->load($results, $this->many);
            }
        }

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
