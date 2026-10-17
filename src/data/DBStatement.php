<?php
namespace obray\data;

use obray\data\exceptions\SqlArrayFailedToLoad;
use obray\data\DBConn;
use obray\data\exceptions\SqlFailedToLoad;
use obray\data\exceptions\SqlFileFailedToLoad;
use obray\data\exceptions\SqlFileNotFound;
use obray\data\exceptions\SqlStringFailedToLoad;

class DBStatement
{
    const VALID_SORT_DIRECTIONS = ['ASC', 'DESC'];

    protected $DBConn;
    protected $queryStrings = [];
    protected $queryParams = [];
    protected $pdoStatements = [];
    protected $results = [];

    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    public function loadSql($sql)
    {
        // A failed reload must not leave the preceding query executable.
        $this->queryStrings = [];
        $this->pdoStatements = [];
        $this->results = [];
        try {
            if (is_array($sql)) {
                $this->loadSqlArray($sql);
            } elseif (!is_string($sql)) {
                throw new SqlFailedToLoad('SQL input must be a string, a file path, or an array containing a SQL string.');
            } elseif ($this->isFilePath($sql)) {
                $this->loadSqlFile($sql);
            } else {
                $this->loadSqlString($sql);
            }
        } catch (\Throwable $e) {
            $this->queryStrings = [];
            throw $e;
        }
        return $this;
    }

    protected function isFilePath($input)
    {
        if (!is_string($input) || $input === '' || strpos($input, "\0") !== false) return false;
        if (is_file($input)) return true;

        // Recognize missing paths too, without treating SQL comments, division,
        // or quoted SQL values as filenames. Bare *.sql names are supported.
        return (bool)preg_match('~^(?:/(?![*/])|\.{1,2}[/\\\\]|[A-Za-z]:[/\\\\])[^\r\n\'";]*$~D', $input)
            || (bool)preg_match('~^[^\s\'";]+\.sql$~iD', $input);
    }

    public function bindValues($params)
    {
        foreach ($params as $key => $param) {
            $param_type = \PDO::PARAM_STR;
            switch(gettype($param)){
                case 'integer': $param_type = \PDO::PARAM_INT; break;
                case 'boolean': $param_type = \PDO::PARAM_BOOL; break;
                case 'NULL':  $param_type = \PDO::PARAM_NULL; break;
            }
            $this->bindValue($key, $param, $param_type);
        }
    }

    public function bindParam($name, &$param, $paramType = \PDO::PARAM_STR)
    {
        $DBParam = new DBParam($name, $param, $paramType);
        $this->addParam($name, $DBParam);
        return $this;
    }

    public function bindValue($name, $param, $paramType = \PDO::PARAM_STR)
    {
        $DBParam = new DBParam($name, $param, $paramType);
        $this->addParam($name, $DBParam);
        return $this;
    }

    private function prepare()
    {
        $pdoConnection = $this->DBConn->getConnection();
        foreach ($this->queryStrings as $index => $queryString) {
            $statement = $this->pdoStatements[$index] ?? $pdoConnection->prepare($queryString);
            if ($statement === false) {
                throw new SqlFailedToLoad('Unable to prepare SQL statement.', 0, $this->prepareError($pdoConnection));
            }
            $this->pdoStatements[$index] = $statement;

            $statement->closeCursor();

            // If first query, bind parameters
            if ($index === 0) {
                foreach ($this->queryParams as $param) {
                    $this->pdoStatements[$index]->bindParam(
                        $param->name,
                        $param->param,
                        $param->type
                    );
                }
            }
        }
    }

    public function execute()
    {
        if (empty($this->queryStrings)) {
            throw new SqlFailedToLoad('No SQL statements have been loaded.');
        }
        $this->prepare();
        if( empty($this->pdoStatements) ) {
            throw new \Exception('No PDOStatements have been prepared. Failed to execute');
        }

        foreach ($this->pdoStatements as $index => $pdoStatement) {
            \obray\core\PerformanceProfile::add('statements_executed');
            $this->results[$index] = $pdoStatement->execute();
        }
        return $this->results;
    }

    public function fetchResults($fetchStyle = \PDO::FETCH_OBJ)
    {
        foreach ($this->pdoStatements as $index => $pdoStatement) {
            $resultCount = $pdoStatement->rowCount();
            if ($this->results[$index] != true) {
                continue;
            }
            $this->results[$index] = [];
            if ($resultCount > 0) {
                $this->results[$index] = $pdoStatement->fetchAll($fetchStyle);
            }
        }
        return $this->results;
    }

    protected function loadSqlString($sqlString)
    {
        if (!is_string($sqlString) || trim($sqlString, " \t\n\r\0\x0B;") === '') {
            throw new SqlStringFailedToLoad('SQL string must not be empty.');
        }
        if (strpos($sqlString, "\0") !== false) {
            throw new SqlStringFailedToLoad('SQL string must not contain NUL bytes.');
        }
        try {
            $dbh = $this->DBConn->getConnection();
            $pdoStatement = $dbh->prepare($sqlString);
            if ($pdoStatement === false) {
                throw new SqlStringFailedToLoad('Unable to prepare SQL string.', 0, $this->prepareError($dbh));
            }
        } catch (\PDOException $e) {
            // Keep SQLSTATE, errorInfo and the original message for server-side diagnostics.
            // ErrorEncoder exposes our message, so do not copy driver/SQL details into it.
            throw new SqlStringFailedToLoad('Unable to load SQL string from the database.', 0, $e);
        }
        $this->parseQueries($sqlString);
        // Retain the validated single statement for execution. Batches still
        // prepare their individual queries, rather than executing the batch twice.
        if (count($this->queryStrings) === 1) {
            $this->pdoStatements[array_key_first($this->queryStrings)] = $pdoStatement;
        }
    }

    private function prepareError(\PDO $connection): \PDOException
    {
        // ERRMODE_SILENT returns false instead of throwing. Preserve the same
        // driver diagnostics without changing the connection's error mode.
        $info = $connection->errorInfo();
        $error = new \PDOException('SQLSTATE[' . ($info[0] ?? 'HY000') . ']: ' . ($info[2] ?? 'SQL preparation failed.'));
        $error->errorInfo = $info;
        return $error;
    }

    protected function loadSqlArray($sqlArray)
    {
        if (!is_array($sqlArray) || !isset($sqlArray['sql']) || !is_string($sqlArray['sql'])
            || trim($sqlArray['sql'], " \t\n\r\0\x0B;") === '' || strpos($sqlArray['sql'], "\0") !== false) {
            throw new SqlArrayFailedToLoad('SQL array must contain a non-empty SQL string under the "sql" key.');
        }
        $this->parseQueries($sqlArray['sql']);
    }

    protected function loadSqlFile($file)
    {
        // Check if file exists before attempting to resolve realpath
        if (!is_file($file)) {
            throw new SqlFileNotFound('SQL file was not found.');
        }

        if (($path = realpath($file)) === false) {
            throw new SqlFileNotFound('SQL file path could not be resolved.');
        }

        // Convert read warnings (including a file disappearing after realpath)
        // into an exception, while preserving the caller's error handler.
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $contents = file_get_contents($path);
        } catch (\ErrorException $e) {
            throw new SqlFileFailedToLoad('Unable to read SQL file.', 0, $e);
        } finally {
            restore_error_handler();
        }
        if ($contents === false || trim($contents, " \t\n\r\0\x0B;") === '' || strpos($contents, "\0") !== false) {
            throw new SqlFileFailedToLoad('SQL file must contain a non-empty SQL string.');
        }

        $this->parseQueries($contents);
    }

    protected function parseQueries($sqlString)
    {
        $queries = explode(';', $sqlString);
        $this->queryStrings = array_map(function ($queryString) {
            $queryString = trim($queryString);
            return $queryString;
        }, $queries);
        // remove empty queries
        $this->queryStrings = array_filter($this->queryStrings);        
    }

    protected function addParam($key, $value)
    {
        // Strip accidental trailing semicolons/whitespace without corrupting placeholder names.
        $key = trim($key, " \t\n\r\0\x0B;");
        if (isset($this->queryParams[$key])) {
            throw new \Exception("A param for key '{$key}' already exists.");
        }
        $this->queryParams[$key] = $value;
    }

    protected static function isInteger($input)
    {
        return (ctype_digit(strval($input)));
    }

}
