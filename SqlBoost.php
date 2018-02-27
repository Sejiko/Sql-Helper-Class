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
class SqlBoost extends PDO{

	private $host;
	private $dbName;
	private $user;
	private $password;
	private $castMethod = 2;
	private $lastQuery = '';
	private $debug;
	private $debugIndex = 1;
	private $debugMessage = array(
		'noVariables' => 'Variables are not allowed in a query!!!',
		'invalidArgs' => 'Table or Columns are not clean!!!',
		'countArgs' => 'Unequal Argument Count!!!'
	);

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '', $debug = false){
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;
		$this->debug = $debug;
		//error_reporting(0);
		try{
			parent::__construct(
					'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $e){
			throw $e;
		}
	}

	public function setDatabaseName($dbName){
		$query = 'use %';
		$this->dbName = $dbName;
		$this->execute($query, $dbName);
	}

	public function getDatabaseNames(){
		$this->setCastMethod(7);
		return $this->execute('SHOW DATABASES;');
	}

	public function isTable($table, $dbName = ''){
		var_dump($dbName);
		var_dump($this->dbName);
		if(empty($dbName)){
			$dbName = $this->dbName;
		}
		var_dump($dbName);
		$systemTables = $this->getTableNames($dbName);
		return in_array($table, $systemTables);
	}

	public function getTableNames($dbname = NULL){
		if(!isset($dbname)){
			$dbname = $this->dbName;
		}

		$dbname = $this->toArray($dbname);

		$this->setCastMethod(7);
		return $this->execute('SHOW TABLES IN %', $dbname);
	}

	public function isColumn($table, $column){
		$systemColumns = $this->getColumnNames($table);

		return in_array($column, $systemColumns);
	}

	public function getColumnNames($table){
		$table = $this->toArray($table);
		$this->setCastMethod(7);

		return $this->execute('DESCRIBE %', $table);
	}

	public function execute($query, $tables = [], $columns = [], $values = []){
		$rawArgs = get_defined_vars();
		$args = $this->prepareQuery($rawArgs);

		if(!$args){
			return false;
		}

		$this->lastQuery = $args['query'];

		$obj = $this->bindValuesToQuery($args['query'], $args['values']);

		if(!$obj->execute()){
			return $this->showDebug();
		}

		return $this->castResult($obj);
	}

	private function bindValuesToQuery($query, $values){
		$obj = parent::prepare($query);

		foreach($values as $key => $value){
			$obj->bindValue($key += 1, $value);
		}

		return $obj;
	}

	private function prepareQuery($args){
		if(!(strpos($args['query'], '$') === false)){
			$this->showDebug($this->debugMessage['noVariables'], 1);
			return false;
		}

		$args['tables'] = $this->toArray($args['tables']);
		$args['columns'] = $this->toArray($args['columns']);
		$args['values'] = $this->toArray($args['values']);

		$rawMergedValues = array_merge($args['columns'], $args['tables']);
		$mergedValues = $this->filterReplacements($rawMergedValues);

		if($mergedValues === false){
			$this->showDebug($this->debugMessage['invalidArgs'], 1);
			return false;
		}

		$countArgs = substr_count($args['query'], '%');
		$countMergedArgs = count($mergedValues);

		if($countArgs !== $countMergedArgs){
			$this->showDebug($this->debugMessage['countArgs'], 1);
			return false;
		}

		$this->replaceValues($args['query'], $mergedValues);

		return $args;
	}

	private function filterReplacements(&$array){
		$result = filter_var_array($array, FILTER_SANITIZE_STRING);
		return ($array === $result) ? $result : false;
	}

	private function replaceValues(&$query, $values){
		foreach($values as $value){
			$query = preg_replace('/\%/', $value, $query, 1);
		}
	}

	public function setCastMethod($number){
		$this->castMethod = intval($number, 10);
	}

	private function castResult($obj){
		$result = $obj->fetchAll($this->castMethod);
		$this->castMethod = 2;
		return $result;
	}

	public function startQueue(){

	}

	public function endQueue(){

	}

	public function create(){

	}

	public function select(){

	}

	public function insert(){

	}

	public function update(){

	}

	public function delete(){

	}

	public function reCast($array, $specialMethod){
		//Different Methods than PDO
		return $array;
	}

	private function showDebug($hint = 'no Hint', $hide = 0){
		if(!$this->debug){
			return false;
		}

		$index = $this->debugIndex;
		if(isset($hide)){
			$index += $hide;
		}
		$debugInfo = debug_backtrace(NULL, 3)[$index];

		$file = $debugInfo['file'] . "\n";
		$line = $debugInfo['line'] . "\n";
		$function = $debugInfo['function'];

		echo '<pre>';
		echo 'File: ' . $file;
		echo 'LineNumber: ' . $line . "\n";
		echo 'DebugHint: ' . $hint . "\n\n";
		echo 'LastQuery: ' . $this->lastQuery . "\n\n";

		echo 'Function: ' . $function . "()\n";
		echo 'Argument List:' . "\n";
		var_export($debugInfo['args']);
		echo '</pre>';

		die();
	}

	static function toArray($var){
		return (is_array($var)) ? $var : [$var];
	}

}
