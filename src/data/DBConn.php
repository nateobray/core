<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\data;

use obray\data\DBStatement;

Class DBConn
{

    private string $username;
    private string $password;
    private string $host;
    private string $port;
    private string $db_name;
    private string $db_engine;
    private string $db_char_set;
    private array $pdo_options;

    /**
     * @var \PDO The PDO Connection
     */
    private $conn;
    private $is_connected = false;
    private static array $pool = [];
    private ?string $dsn = null;
    private ?string $pool_key = null;

    public function __construct(
        $host,
        $username,
        $password,
        $db_name,
        $port = '3306',
        $db_engine = 'innoDB',
        $char_set = "utf8",
        array $pdo_options = []
    ) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->db_name = $db_name;
        $this->port = $port;
        $this->db_engine = $db_engine;
        $this->db_char_set = $char_set;
        $this->pdo_options = $pdo_options + [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => true
        ];
        $this->buildDsn();
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
        $this->disconnect();
        $this->buildDsn();
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
        $this->disconnect();
        $this->buildDsn();
    }

    public function setHost($host)
    {
        $this->host = $host;
        $this->disconnect();
        $this->buildDsn();
    }

    public function setPort($port)
    {
        $this->port = $port;
        $this->disconnect();
        $this->buildDsn();
    }

    public function setDBName($name)
    {
        $this->db_name = $name;
        $this->disconnect();
        $this->buildDsn();
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

    public function setOption(int $attribute, $value): void
    {
        $this->pdo_options[$attribute] = $value;
        if ($this->is_connected) {
            $this->conn->setAttribute($attribute, $value);
        }
    }

    /**
     * @param bool $reconnect
     * @return \PDO
     */
    public function connect($reconnect = false)
    {
        if ($reconnect && $this->pool_key !== null) {
            unset(self::$pool[$this->pool_key]);
            $this->conn = null;
            $this->is_connected = false;
        }

        if ($this->pool_key === null) {
            $this->buildDsn();
        }

        if (!$this->is_connected) {
            if (isset(self::$pool[$this->pool_key])) {
                $this->conn = self::$pool[$this->pool_key];
                try {
                    $this->conn->query('SELECT 1');
                    $this->is_connected = true;
                } catch (\PDOException $e) {
                    unset(self::$pool[$this->pool_key]);
                    $this->conn = null;
                }
            }
            if (!$this->is_connected) {
                try {
                    $this->conn = new \PDO(
                        $this->dsn,
                        $this->username,
                        $this->password,
                        $this->pdo_options
                    );
                    $this->is_connected = true;
                    self::$pool[$this->pool_key] = $this->conn;
                } catch (\PDOException $e) {
                    throw $e;
                }
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
            if ($this->pool_key !== null) {
                unset(self::$pool[$this->pool_key]);
                $this->pool_key = null;
            }
            $this->conn = null;
            $this->is_connected = false;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function buildDsn(): void
    {
        $this->dsn = 'mysql:host=' . $this->host
            . ';port=' . $this->port
            . ';dbname=' . $this->db_name
            . ';charset=' . $this->db_char_set;
        $this->pool_key = hash('sha256', $this->dsn . $this->username . $this->password);
    }
}
