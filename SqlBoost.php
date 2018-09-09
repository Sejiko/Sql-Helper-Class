<?php

/**
 * SQLBoost:
 *
 * This class makes SQL a lot easier and
 * provides you with full controll over it.
 *
 * @author Florian Heidebrecht
 *
 */
class SqlBoost extends PDO {
    private $host;
    private $dbName;
    private $user;
    private $password;
    private $placeholder = '$';
    private $lastQuery = '';
    private $sqlQuery = '_';
    private $sqlQueryInformation = [];
    private $sqlQuerySubstitution = [];
    private $sqlResponseMethod = 2;
    private $nullStatements = ['isnull' => ' IS NULL ', 'isnotnull' => ' IS NOT NULL '];

    public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '') {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->user = $user;
        $this->password = $password;

        try {
            parent::__construct(
                    'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
            );
        } catch (PDOException $e) {
            throw $e;
        }
    }

//TODO get Last (n) results
    /**
     *
     * @param type $query
     * @param type $information
     * @param type $substitution
     * @param type $responseMethod
     * @return boolean
     */
    public function execute($query = '', $information = [], $substitution = [], $responseMethod = PDO::FETCH_ASSOC) {
        if(empty($query)) {
            return $this->executeMethodChain();
        }

        $this->escapeQuery($query, $this->toArray($substitution));
        $obj = parent::prepare($query);
        if (!$obj->execute($information)) {
            return false;
        }

        return $this->fetchResults($obj, $responseMethod);
    }
    
    private function executeMethodChain($responseMethod = PDO::FETCH_ASSOC) {
        return $this->execute($this->sqlQuery, $this->sqlQueryInformation, $this->sqlQuerySubstitution, $this->sqlResponseMethod);
    }

    private function escapeQuery(&$query, $substitutions = []) {
        $replacements = preg_grep('/^\w+$/', $substitutions);
        if ($substitutions != $replacements) {
            throw new Exception('Invalid tables in Query: [' . $query . ']');
        }

        foreach ($replacements as $replacement) {
            $query = preg_replace('/\\' . $this->placeholder . '/', $replacement, $query, 1);
        }

        $this->lastQuery = $query;
    }

    function getLastQuery() {
        return $this->lastQuery;
    }

    private function fetchResults($result, $responseMethod) {
        return (is_callable($responseMethod)) ? call_user_func($responseMethod, $result) : $result->fetchAll($responseMethod);
    }

    public function setDatabaseName($dbName) {
        $query = 'use $';
        $this->dbName = $dbName;
        $this->execute($query, [], $dbName);
    }

    public function getDatabaseNames() {
        return $this->execute('SHOW DATABASES;', [], [], PDO::FETCH_COLUMN);
    }

    public function isTable($table, $dbName = '') {
        if (empty($dbName)) {
            $dbName = $this->dbName;
        }
        $systemTables = $this->getTableNames($dbName);

        return in_array($table, $systemTables);
    }

    public function getTableNames($dbName = '') {
        if (empty($dbName)) {
            $dbName = $this->dbName;
        }

        return $this->execute('SHOW TABLES IN $', [], $dbName, PDO::FETCH_COLUMN);
    }

    public function isColumn($table, $column) {
        $systemColumns = $this->getColumnNames($table);

        return in_array($column, $systemColumns);
    }

    public function getColumnNames($table) {
        return $this->execute('DESCRIBE $', [], $table, PDO::FETCH_COLUMN);
    }

    public function getParams($argsCount, $char, $default) {
        $paramString = rtrim(str_repeat($char . ',', $argsCount), ',');
        return (strlen($paramString) > 0) ? $paramString : $default;
    }

    private function substituteNullStatemennts($keyword) {
        return (in_array($keyword, $this->nullStatements)) ? $this->nullStatements[$keyword] : false;
    }

    public function where() {
        
    }
    
    /**
     *
     * @param type $whereConditions
     * @param type $informations
     * @return string
     */
    public function getWhereClause($whereConditions) {
        $whereClause = 'WHERE ';
        if (empty($whereConditions)) {
            return $whereClause . '1 = 1;';
        }

        $last = key(array_slice($whereConditions, -1, 1, TRUE));
        foreach ($whereConditions as $logicOperator => $conditions) {
            $lastElement = end($conditions)[0];
            foreach ($conditions as $comparisonOperator => $columns) {
                foreach ($columns as $column) {
                    $checkNull = $this->substituteNullStatemennts($comparisonOperator);
                    if ($checkNull) {
                        $whereClause .= $column . $checkNull;
                    } else {
                        $whereClause .= $column . ' ' . $comparisonOperator . ' ? ';
                    }

                    if (!($last == $logicOperator && $lastElement == $column)) {
                        $whereClause .= $logicOperator . ' ';
                    }
                }
            }
        }

        return $whereClause;
    }

    public function select($table, $columns = []) {
        $this->sqlQuery = 'SELECT ' . $this->getParams(count($columns), '$', '*') . ' FROM $';
        $this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, $columns, [$table]);
        return $this;
    }

    public function insert() {
        //TODO implement
    }

    public function update() {
        //TODO implement
    }

    public function delete() {
        //TODO implement
    }

    //TODO implement Transactions
    public function checkTransaction() {
        //TODO implement
    }

    static function toArray($var) {
        return (is_array($var)) ? $var : [$var];
    }

}
?>
