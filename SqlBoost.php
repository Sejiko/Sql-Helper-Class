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
    private $sqlQueries = [];
    private $sqlQueryInformation = [];
    private $sqlQuerySubstitution = [];
    private $sqlResponseMethod = PDO::FETCH_ASSOC;
    private $startWhere = true;
    private $nullStatements = ['isnull' => 'IS NULL', 'isnotnull' => 'IS NOT NULL'];
    private $pdoSettings = array(PDO::ATTR_TIMEOUT => 1);

    public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '', array $pdoSettings = []) {
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
    //TODO make multi instances maybe 2 Databases or two different users; getInstanceOf('Database1'), getInstanceOf('Database2') => should also be singelton
    public static function singleton($host = 'localhost', $dbName = '', $user = 'root', $password = '', array $pdoSettings = []): SQLBoost {
	self::$singleton = self::$singleton ?? new SQLBoost($host, $dbName, $user, $password);

	return self::$singleton;
    }
    
    /*
     * Add a new Query to the executionStack.
     * Associates $rawInformation to Array.
     * 
     */
    public function addQuery(string $query, array $rawInformation = []) {
	$this->executionStack[] = $query;
	$this->informationStack[] = $rawInformation;
    }

    /**
     * Executes an SQL Query and prepares Data
     *
     * Example usage:
     * 1: $sql->execute($query,$data)
     * 2: $sql->select('table')->where('id', 1)->execute()
     *
     * @param string $query -> optional: if not set a method Chain gets executed otherwise the query.
     * @param array $information -> optional: replace each value in the SQL string with "?" and provide an array with values
     * @param array $substitutions -> optional: all tablenames and columnnames are escaped with an "$" instead of "?"
     * @param number/callable $responseMethod -> number(PDO::FETCH_ASSOC)/Callback specify a return format. callback($result)
     * @return boolean
     */
    public function execute(string $query = '', $information = [], $substitutions = [], $responseMethod = PDO::FETCH_ASSOC) {
	if (empty($query) && !empty($this->sqlQueries)) {
	    return $this->executeMethodChain($responseMethod);
	}

	$this->escapeQuery($query, $substitutions);

	$obj = parent::prepare($query);
	$executionState = $obj->execute($information);
	$this->createDebugInformation($query, $information, $executionState);
	$this->reset();

	return $this->fetchResults($obj, $responseMethod);
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

    private function executeMethodChain($responseMethod) {
	return $this->execute(implode(';', $this->sqlQueries), $this->sqlQueryInformation, $this->sqlQuerySubstitution, $this->sqlResponseMethod, $responseMethod);
    }

    //TODO change getLastQuery name
    private function createDebugInformation($query, &$informations, $executionState) {
	foreach ($informations as $information) {
	    $query = preg_replace('/\?/', '`' . $information . '`', $query, 1);
	}

	if (!empty($query)) {
	    $this->lastQueries[] = array('state' => $executionState, $query);
	}
    }

    private function fetchResults($result, $responseMethod) {
	return (is_callable($responseMethod)) ? call_user_func($responseMethod, $result) : $result->fetchAll($responseMethod);
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
	$this->sqlQueryInformation = [];
	$this->sqlQuerySubstitution = [];
	$this->sqlResponseMethod = 2;
    }

    public function enqueuSql(string $query = '', $information = [], $substitution = []): object {
	$this->sqlQueries[] = $query;
	$this->sqlQueryInformation = array_merge($this->sqlQueryInformation, $information);
	$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, $substitution);

	return $this;
    }

    /**
     * query unescaped values
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
    public function setDatabaseName(string $dbName) {
	$query = 'use $';
	$this->dbName = $dbName;
	return $this->execute($query, [], $dbName);
    }

    public function getDatabaseNames() {
	return $this->execute('SHOW DATABASES;', [], [], PDO::FETCH_COLUMN);
    }

    /**
     *
     * @param string $table -> specify the tablename which could be in database
     * @param type $dbName -> optional if empty the internal dbname gets used
     * @return bool
     */
    public function isTable($table, $dbName = '') {
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

	return $this->execute('SHOW TABLES IN $', [], $dbName, PDO::FETCH_COLUMN);
    }

    /**
     * check if a column is in a table
     *
     * @param type $table
     * @param type $column
     * @return type
     */
    public function isColumn($table, $column) {
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
	return $this->execute('DESCRIBE $', [], $table, PDO::FETCH_COLUMN);
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
?>
