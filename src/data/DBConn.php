<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\data;

use obray\data\DBStatement;
use obray\core\CoreProjectEnum;

Class DBConn
{

    private string $username;
    private string $password;
    private string $host;
    private string $port;
    private string $db_name;
    private string $db_engine;
    private string $db_char_set;

    /**
     * @var \PDO The PDO Connection
     */
    private $conn;
    private $is_connected = false;

    public function __construct(
        $host,
        $username,
        $password,
        $db_name,
        $port = '3306',
        $db_engine = 'innoDB',
        $char_set = "utf8"
    ) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->db_name = $db_name;
        $this->port = $port;
        $this->db_engine = $db_engine;
        $this->db_char_set = $char_set;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function setDBName($name)
    {
        $this->db_name = $name;
    }

    public function getDBName()
    {
        return $this->db_name;
    }

    public function getDBEngine()
    {
        return $this->db_engine;
    }

    public function getDBCharSet()
    {
        return $this->db_char_set;
    }

    /**
     * @param bool $reconnect
     * @return \PDO
     */
    public function connect($reconnect = false)
    {
        $this->conn;
        if (!isSet($this->conn) || $reconnect) {
            try {
                $this->conn = new \PDO(
                    'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8',
                    $this->username,
                    $this->password,
                    array(
                        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                        \PDO::ATTR_PERSISTENT => true
                    ));
                $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->is_connected = true;
                
            } catch (\PDOException $e) {
                echo 'ERROR: ' . $e->getMessage();
                exit();
            }
        }
        return $this->conn;
    }

    /**
     * @return \PDO
     */
    public function getConnection()
    {
        if (!$this->is_connected) {
            $this->connect();
        }
        return $this->conn;
    }

    public function isConnected()
    {
        return $this->is_connected;
    }

    public function beginStatement($sql)
    {
        $DBStatement = new DBStatement($this);
        $DBStatement->loadSql($sql);
        return $DBStatement;
    }

    public function __call($name, $arguments = array())
    {
        $conn = $this->connect();
        if (method_exists($conn, $name)) {
            return $conn->$name(...$arguments);
        }
    }

    public function run($sql, $bind = [], $fetchStyle = \PDO::FETCH_OBJ)
    {
        $DBStatement = $this->beginStatement($sql);
        $DBStatement->bindValues($bind);
        $DBStatement->execute();
        return $DBStatement->fetchResults($fetchStyle);
    }

    public function beginTransaction()
    {
        $conn = $this->getConnection();
        $conn->beginTransaction();
    }

    public function commitTransaction()
    {
        $conn = $this->getConnection();
        $conn->commit();
    }

    public function rollbackTransaction()
    {
        $conn = $this->getConnection();
        $conn->rollBack();
    }

    public function inTransaction()
    {
        $conn = $this->getConnection();
        return $conn->inTransaction();
    }

    public function disconnect() {
        if ($this->conn !== null) {
            $this->conn = null;
            $this->is_connected = false;
        }
    }
}