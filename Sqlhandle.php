<?php

/**
 * Sqlhandle: A class to simplify the usage of the PDO class
 *
 * @author Florian Heidebrecht
 * edited by Philipp Caldwell
 *
 */
class Sqlhandle extends PDO{

	private $host;
	private $dbName;
	private $user;
	private $password;
	public $lastQuerry = '';

	/**
	 * Class Constructor
	 * @param string $host set Host
	 * @param string $dbName set DatabaseName
	 * @param string $user set User
	 * @param string $password set Password
	 */
	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = ''){
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;

		$this->conn();
	}

	/**
	 *
	 * @param string $value
	 * @return bool
	 */
	public function setDbName($value){
		$this->dbName = $value;
		return $this->conn();
	}

	private function conn(){
		try{
			parent::__construct(
					'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $exception){
			return $exception;
		}

		return true;
	}

	public function getDatabaseNames(){
		return $this->prepare('SHOW DATABASES');
	}

	public function getTableNames(){
		$database[] = $this->dbName;
		$query = 'SHOW TABLES IN %d';

		return $this->prepare($query, NULL, NULL, NULL, $database);
	}

	/**
	 *
	 * @param string $table
	 * @return boolean
	 */
	public function tableExists($table){
		$tableNames = $this->getTableNames();

		if(in_array(strtolower($table), $tableNames)){
			return true;
		}

		return false;
	}

	/**
	 *
	 * @param string $tableName
	 * @return array
	 */
	public function getColumnNames($tableName){
		$table[] = $tableName;
		$query = 'Describe %1t';
		return $this->prepare($query, $table);
	}

	/**
	 *
	 * @param string $table
	 * @param array $column
	 * @return boolean
	 */
	public function columnExists($table, $column){
		$columnNames = $this->getColumnNames($table);

		if(in_array(strtolower($column), $columnNames)){
			return true;
		}

		return false;
	}

	/**
	 * Core Function
	 *
	 * @param string $query
	 * @param array $tables
	 * @param array $columns
	 * @param array $values
	 * @param string $database
	 * @return mixed
	 */
	public function prepare($query, $tables = [], $columns = [], $values = [], $database = []){
		$this->preEvaluation($query, $tables, $columns, $database);
		$obj = parent::prepare($query);
		$this->lastQuerry = $query;

		foreach($values as $key => $value){
			$obj->bindValue($key += 1, $value);
		}

		if(!$obj->execute()){
			return $this->errorInfo();
		}
		$result = $obj->fetchAll(PDO::FETCH_ASSOC);

		return $result;
	}

	/**
	 * Dont touch it!
	 * @param type $rawquery
	 * @param type $tables
	 * @param type $columns
	 * @param type $database
	 * @return boolean
	 */
	private function preEvaluation(&$rawquery, $tables = [], $columns = [], $database = []){
		$rawquery = preg_replace_callback("/%(?'amt'\d+)(?'el'\w+|\?)/", function($matches){

			if($matches['el'] != '?'){
				$matches['el'] = '%' . $matches['el'];
			}

			return implode(',', array_fill(0, $matches['amt'], $matches['el']));
		}, $rawquery);

		$this->bindValuesToQuery($rawquery, 't', $tables);
		$this->bindValuesToQuery($rawquery, 'c', $columns);
		$this->bindValuesToQuery($rawquery, 'd', $database);

		return true;
	}

	/**
	 * Dont touch it!
	 * @param type $query
	 * @param type $label
	 * @param type $data
	 * @return boolean
	 */
	public function bindValuesToQuery(&$query, $label, $data = []){
		foreach($data as $value){
			if(preg_match('/^\w+$/', $value)){
				$query = preg_replace('/%' . $label . '/', $value, $query, 1);
			} else{
				return false;
			}
		}
		return $query;
	}

	/**
	 * Dont touch it
	 * @param array $array
	 * @param char $label
	 * @return string
	 */
	private function getParameter(&$array, $label){
		$count = count($array);
		return ($count > 0) ? '%' . $count . $label : '*';
	}

	/**
	 *
	 * @param string $name
	 * @param string $what DATABASE || TABLE
	 * @return mixed
	 */
	public function create($name, $what){
		$query = 'CREATE %1t %c';
		return $this->prepare($query, [$what], [$name]);
	}

	/**
	 *
	 * @param string $tableName
	 * @param array $columns
	 * @param integer $id
	 * @return array
	 */
	public function select($tableName, $columns, $id){
		$table[] = $tableName;
		$replacer = $this->getParameter($columns, 'c');

		$query = 'SELECT ' . $replacer . ' FROM %1t ' . ' WHERE id = %1?';

		return $this->prepare($query, $table, $columns, [$id]);
	}

	/**
	 *
	 * @param sting $tableName
	 * @param array $columns
	 * @param array $values
	 * @param integer $id
	 */
	public function insert($tableName, $columns, $values){
		$table[] = $tableName;
		$replacer = $this->getParameter($columns, 'c');
		$valueReplacer = $this->getParameter($columns, '?');
		$query = 'INSERT INTO %1t (' . $replacer . ') VALUES (' . $valueReplacer . ');';

		return $this->prepare($query, $table, $columns, $values);
	}

	/**
	 *
	 * @param string $tableName
	 * @param array $columns
	 * @param array $values
	 * @param string $where
	 */
	public function update($tableName, $columns, $values, $where = 'WHERE 1=1'){
		$table[] = $tableName;
		$cloumnsString = implode(' ,', array_map(function(){
					return '%1c=?';
				}, $columns));
		$query = 'UPDATE %1t SET ' . $cloumnsString . ' ' . $where . ';';

		$this->prepare($query, $table, $columns, $values);
	}

	/**
	 *
	 * @param string $tableName
	 * @param integer $id
	 * @return mixed
	 */
	public function delete($tableName, $id){
		$table[] = $tableName;
		$query = 'DELETE FROM %1t WHERE id = %1?;';

		return $this->prepare($query, $table, [], [$id]);
	}

	/**
	 *
	 * @param array $data
	 * @param string $columnName
	 * @param string $mode
	 * @return customArray
	 */
	public function fetchResult($data, $columnName = 'id', $mode = ''){
		$structuredResult = array();
		foreach($data as $value){
			$structuredResult[$value[$columnName]] = $value;
		}

		return $structuredResult;
	}

}

?>
