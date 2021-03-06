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
	private $placeholder = '$';
	private $lastQuery = '';
	private $sqlQuery = '_';
	private $sqlQueryInformation = [];
	private $sqlQuerySubstitution = [];
	private $sqlResponseMethod = 2;
	private $startWhere = true;
	private $nullStatements = ['isnull' => 'IS NULL', 'isnotnull' => 'IS NOT NULL'];

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '') {
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;

		try {
			parent::__construct(
					'mysql:host='.$this->host.';dbname='.$this->dbName.';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $e) {
			throw $e;
		}
	}

	/**
	 * Executes an SQL Query and prepares Data
	 *
	 * Example usage:
	 * 1: $sql->execute($query,$data)
	 * 2: $sql->select('table')->where('id', 1)->execute($query,$data)
	 *
	 * @param string $query -> optional: if not set a method Chain gets executed otherwise the query.
	 * @param array $information -> optional: replace each value in the SQL string with "?" and provide an array with values
	 * @param array $substitution -> optional: all tablenames and columnnames are escaped with an "$" instead of "?"
	 * @param number/callable $responseMethod -> number(PDO::FETCH_ASSOC)/Callback specify a return format. callback($result)
	 * @return boolean
	 */
	public function execute($query = '', $information = [], $substitution = [], $responseMethod = PDO::FETCH_ASSOC) {
		if(empty($query)) {
			return $this->executeMethodChain();
		}

		$this->escapeQuery($query, $this->toArray($substitution));
		$obj = parent::prepare($query);
		if(!$obj->execute($information)) {
			return false;
		}

		$this->reset();
		return $this->fetchResults($obj, $responseMethod);
	}

	private function executeMethodChain($responseMethod = PDO::FETCH_ASSOC) {
		return $this->execute($this->sqlQuery, $this->sqlQueryInformation, $this->sqlQuerySubstitution, $this->sqlResponseMethod);
	}

	private function escapeQuery(&$query, $substitutions = []) {
		$replacements = preg_grep('/^\w+$/', $substitutions);
		if($substitutions != $replacements) {
			throw new Exception('Invalid tables in Query: ['.$query.']');
		}

		foreach($replacements as $replacement) {
			$query = preg_replace('/\\'.$this->placeholder.'/', $replacement, $query, 1);
		}

		$this->lastQuery = $query;
	}

	private function fetchResults($result, $responseMethod) {
		return (is_callable($responseMethod)) ? call_user_func($responseMethod, $result) : $result->fetchAll($responseMethod);
	}

	private function makeParamString($argsCount, $char, $default = '') {
		$paramString = rtrim(str_repeat($char.',', $argsCount), ',');
		return (strlen($paramString) > 0) ? $paramString : $default;
	}

	private function substituteNullStatements($keyword) {
		return (isset($this->nullStatements[$keyword])) ? $this->nullStatements[$keyword] : $keyword;
	}

	private function reset() {
		$this->sqlQuery = '_';
		$this->sqlQueryInformation = [];
		$this->sqlQuerySubstitution = [];
		$this->sqlResponseMethod = 2;
	}

	/**
	 * TODO Raw Query + Query with Values
	 * @return string $queryString
	 */
	public function getLastQuery() {
		return $this->lastQuery;
	}

	/**
	 * set the internal db name which should be used.
	 * can allways be switched.
	 *
	 * @param type $dbName
	 * @return bool
	 */
	public function setDatabaseName($dbName) {
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
		if(empty($dbName)) {
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
		if(empty($dbName)) {
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
		$this->sqlQuery = 'SELECT '.$this->makeParamString(count($columns), '$', '*').' FROM $';
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

		$this->sqlQuery = 'INSERT INTO $ ('.$substituteParams.') VALUES('.$preparedValues.')';
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
		$arguments = array_fill_keys($columns, '?');
		$paramString = str_replace('%3F', '?', http_build_query($arguments, null, ','));

		$this->sqlQuery = 'UPDATE $ SET '.$paramString;
		echo $this->sqlQuery;
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
		$this->sqlQuery = 'DELETE FROM $';
		$this->sqlQuerySubstitution = array_merge($this->sqlQuerySubstitution, [$table]);

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
		$logicOperator = (in_array(strtoupper($logicOperator), ['OR', 'AND'])) ? $logicOperator : false;

		$prefix = ($this->startWhere) ? ' WHERE' : $logicOperator;
		$this->startWhere = false;
		$this->sqlQuery .= $prefix.' '.$column.' '.$this->substituteNullStatements($operator).' ';

		if($value) {
			$this->sqlQuery .= '? ';
			$this->sqlQueryInformation = array_merge($this->sqlQueryInformation, [$value]);
		}

		return $this;
	}

	public function checkTransaction() {
		//TODO implement
	}

	static function toArray($var) {
		return (is_array($var)) ? $var : [$var];
	}
}

?>
