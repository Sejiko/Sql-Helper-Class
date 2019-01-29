<?php

/**
 * SQLBoost:
 *
 * This class boosts your expierience with SQL
 * and makes it alot safer to use a database.
 *
 * @author Florian Heidebrecht
 *
 */
class SqlBoost extends PDO {
    private $host;
    private $dbName;
    private $user;
    private $password;
    private static $singleton;
    private $placeholder = '$';
    private $lastQueries;
    private $executionStack = [];
    private $informationStack = [];
    private $internalExecutionStack = [];
    private $internalInformationStack = [];
    private $internalExecution = false;
    private $sqlQueries = [];
    private $fetchMethod = PDO::FETCH_ASSOC;
    private $executionState;
    private $sqlQueryInformation = [];
    private $sqlQuerySubstitution = [];
    private $sqlResponseMethod = PDO::FETCH_ASSOC;
    private $startWhere = true;
    private $nullStatements = ['isnull' => 'IS NULL', 'isnotnull' => 'IS NOT NULL'];
    private $pdoSettings = array(PDO::ATTR_TIMEOUT => 1);

    public function __construct($host = 'localhost', $dbName = 'test', $user = 'root', $password = '', array $pdoSettings = []) {
	$this->host = $host;
	$this->dbName = $dbName;
	$this->user = $user;
	$this->password = $password;

	if (!empty($pdoSettings)) {
	    $this->pdoSettings = $pdoSettings;
	}

	parent::__construct(
		'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, $this->pdoSettings
	);
    }

    /**
     * New instance of SQLBoost
     *
     * @param string $host optional default localhost
     * @param string $dbName optional default ''
     * @param string $user optional default root
     * @param string $password optional default ''
     *
     */
    public static function singleton($host = 'localhost', $dbName = '', $user = 'root', $password = '', array $pdoSettings = []): SQLBoost {
	self::$singleton = self::$singleton ?? new SQLBoost($host, $dbName, $user, $password);

	return self::$singleton;
    }

    /*
     * Add a new Query to the executionStack.
     * Associates $values to it.
     * 
     */

    public function addQuery(string $query, array $values = []): SqlBoost {
	$this->executionStack[] = $query;
	$this->informationStack = array_merge($this->informationStack, $values);

	return $this;
    }

    /*
     * addValues to the Query
     */

    public function addValues(array $values): SqlBoost {
	$this->informationStack = array_merge($this->informationStack, $values);
	return $this;
    }

    /*
     * This function replaces the given prefix in your Query
     * with an Escaped version of your Table names.
     * 
     */

    public function escapeTables(string $prefix, ...$tables) {
	//TODO escape tables in query
	return $this;
    }

    /*
     * This function replaces the given prefix in your Query
     * with an Escaped version of your Column names.
     * 
     */

    public function escapeColumns(string $prefix, ...$tables) {
	//TODO escape tables in query
	return $this;
    }

    /**
     * Executes an SQL ExecutionStack and prepares Data
     *
     * @return array
     */
    public function execute($responseMethod = PDO::FETCH_ASSOC) {
	//TODO if internal Execution is triggered (bool) get Only last stack elements and dont hard reset just remove last elements.
	$query = '';
	$informationStack = [];
	if ($this->internalExecution) {
	    $query = implode(';', $this->internalExecutionStack);
	    $informationStack = $this->internalInformationStack;
	} else {
	    $query = implode(';', $this->executionStack);
	    $informationStack = $this->informationStack;
	}
	$obj = parent::prepare($query);

	$this->executionState = $obj->execute($informationStack);
	if (!$this->internalExecution) {
	    $this->createDebugInformation($query, $this->informationStack, $this->executionState);
	}

	$this->reset();

	return $this->fetchResults($obj);
    }

    function getExecutionState() {
	return $this->executionState;
    }

    private function createDebugInformation($query, &$informations, $executionState) {
	foreach ($informations as $information) {
	    $query = preg_replace('/\?/', '`' . $information . '`', $query, 1);
	}

	if (!empty($query)) {
	    $this->lastQueries[] = array('state' => $executionState, $query);
	}
    }

    public function setFetchmethod($fetchmethod): SqlBoost {
	$this->fetchMethod = $fetchmethod;
	return $this;
    }

    private function fetchResults($result) {
	return (is_callable($this->fetchMethod)) ? call_user_func($this->fetchMethod, $result) : $result->fetchAll($this->fetchMethod);
    }

    private function escapeQuery(&$query, $substitutions = []) {
	$substitutions = $this->toArray($substitutions);
	$replacements = preg_grep('/^\w+$/', $substitutions);
	if ($substitutions != $replacements) {
	    throw new Exception('Invalid tablenames for Query: [' . $query . ']');
	}

	foreach ($replacements as $replacement) {
	    $query = preg_replace('/\\' . $this->placeholder . '/', $replacement, $query, 1);
	}
    }

    private function makeParamString($argsCount, $char, $default = '') {
	$paramString = rtrim(str_repeat($char . ',', $argsCount), ',');
	return (strlen($paramString) > 0) ? $paramString : $default;
    }

    private function makePreparedStatement($keyword, $value = NULL) {
	if ($keyword == 'nullable') {
	    return $qstr = (is_null($value)) ? $this->nullStatements['isnull'] : ' = ? ';
	} elseif (in_array($keyword, array_keys($this->nullStatements))) {
	    return $this->nullStatements[$keyword] . ' ';
	} else {
	    $this->sqlQueryInformation = array_merge($this->sqlQueryInformation, [$value]);
	    return $keyword . ' ? ';
	}
    }

    private function reset() {
	$this->sqlQueries = [];
	$this->executionStack = [];
	$this->informationStack = [];
	$this->sqlQueryInformation = [];
	$this->sqlQuerySubstitution = [];
	$this->sqlResponseMethod = 2;
	$this->internalExecution = false;
    }

    /**
     * query values
     * hide non failed sqls but make placeholder
     *
     * @return string $queryString
     */
    public function getDebug($max = NULL, $showOnlyFailed = true) {
	$output = '</br>Debug Information:</br></br>';
	$amount = count($this->lastQueries) - 1;
	foreach ($this->lastQueries as $key => $lastQuery) {
	    $printState = '(' . (($lastQuery['state']) ? 'True' : 'False') . ')';
	    if ($max && (($amount - $max) >= $key)) {
		$output .= 'skipped: state' . $printState . '</br>';
		continue;
	    }

	    if ($showOnlyFailed && $lastQuery['state']) {
		$output .= 'skipped' . $printState . '</br>';
		continue;
	    } else {
		$output .= 'query' . $printState . ':</br>' . $lastQuery[0] . '</br></br>';
	    }
	}

	return $output;
    }

    /**
     * set the internal db name which should be used.
     * can allways be switched.
     *
     * @param type $dbName
     * @return bool
     */
    public function setDatabaseName(string $dbName): bool {
	$query = 'use ' . $dbName;
	$this->addQuery($query);
	$this->execute();
	return $this->getExecutionState();
    }

    public function getDatabaseNames() {
	$this->addQuery('SHOW DATABASES;');
	$this->setFetchmethod(PDO::FETCH_COLUMN);

	$this->execute();
	return $this->getExecutionState();
    }

    /**
     *
     * @param string $table -> specify the tablename which could be in database
     * @param type $dbName -> optional if empty the internal dbname gets used
     * @return bool
     */
    public function isTable($table, $dbName = ''): bool {
	if (empty($dbName)) {
	    $dbName = $this->dbName;
	}
	$systemTables = $this->getTableNames($dbName);

	return in_array($table, $systemTables);
    }

    /**
     * get all tablenames in a db
     *
     * @param type $dbName -> optional if empty the internal dbname gets used
     * @return type
     */
    public function getTableNames($dbName = '') {
	if (empty($dbName)) {
	    $dbName = $this->dbName;
	}

	return $this->addQuery('SHOW TABLES IN ' . $dbName)->setFetchmethod(PDO::FETCH_COLUMN)->execute();
    }

    /**
     * check if a column is in a table
     *
     * @param type $table
     * @param type $column
     * @return type
     */
    public function isColumn($table, $column): bool {
	$systemColumns = $this->getColumnNames($table);

	return in_array($column, $systemColumns);
    }

    /**
     * get table names in current db.
     *
     * @param type $table
     * @return type
     */
    public function getColumnNames($table) {
	return $this->addQuery('DESCRIBE ' . $table)->setFetchmethod(PDO::FETCH_COLUMN)->execute();
    }

    /**
     * select part of query
     *
     * $sql->select('tablename', ['column1', 'column2']);
     *
     * @param type $table
     * @param type $columns
     * @return $this
     */
    public function select($table, $columns = []) {
	$columns = $this->toArray($columns);
	$this->sqlQueries[] = 'SELECT ' . $this->makeParamString(count($columns), '$', '*') . ' FROM $';
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, $columns, [$table]);
	return $this;
    }

    /**
     * insert statement of query
     *
     * $sql->insert('tablename', ['column1', 'column2'], [1,2])
     * @param type $table
     * @param type $columns
     * @param type $values
     * @return $this
     */
    public function insert($table, $columns, $values) {
	$substituteParams = $this->makeParamString(count($this->toArray($columns)), '$');
	$preparedValues = $this->makeParamString(count($this->toArray($columns)), '?');

	$this->sqlQueries[] = 'INSERT INTO $ (' . $substituteParams . ') VALUES(' . $preparedValues . ')';
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, [$table], $this->toArray($columns));
	$this->sqlQueryInformation = array_merge($this->sqlQueryInformation, $this->toArray($values));

	return $this;
    }

    /**
     * update statement of query
     *
     * @param type $table
     * @param type $columns
     * @param type $values
     * @return $this
     */
    public function update($table, $columns, $values) {
	$arguments = array_fill_keys($this->toArray($columns), '?');
	$paramString = str_replace('%3F', '?', http_build_query($arguments, null, ','));

	$this->sqlQueries[] = 'UPDATE $ SET ' . $paramString;
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, [$table], $this->toArray($columns));
	$this->sqlQueryInformation = array_merge($this->sqlQueryInformation, $this->toArray($values));

	return $this;
    }

    /**
     * delete statement of query
     *
     * @param type $table
     * @return $this
     */
    public function delete($table) {
	$this->sqlQueries[] = 'DELETE FROM $';
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, [$table]);

	return $this;
    }

    /**
     * limit statement of query
     *
     * @param type $table
     * @return $this
     */
    public function limit($max) {
	$this->sqlQueries[count($this->sqlQueries) - 1] .= ' Limit $';
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, [$max]);

	return $this;
    }

    /**
     * makes a simple where clause for the method chain
     *
     * @param type $column
     * @param type $operator
     * @param type $value
     * @param type $logicOperator
     * @return $this
     */
    public function where($column, $operator = '=', $value = null, $logicOperator = 'AND') {
	$prefix = ($this->startWhere) ? ' WHERE' : $logicOperator;
	$this->startWhere = false;

	$this->sqlQueries[count($this->sqlQueries) - 1] .= $prefix . ' ' . $column . ' ';
	$this->sqlQueries[count($this->sqlQueries) - 1] .= $this->makePreparedStatement($operator, $value) . ' ';

	return $this;
    }

    public function checkTransaction() {
	try {
	    $this->beginTransaction();
	} catch (Exception $exc) {
	    return false;
	}
	if ($this->inTransaction()) {
	    $this->commit();
	    return true;
	}
	return false;
    }

    static function toArray(&$var) {
	return (is_array($var)) ? $var : [$var];
    }

}
