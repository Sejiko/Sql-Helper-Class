<?php

class Sqlhandle extends PDO{

	private $host;
	private $dbName;
	private $user;
	private $password;
	public $lastQuerry = '';

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = ''){
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;

		$this->conn();
	}

	public function setDbName($value){
		$this->dbName = $value;
		$this->conn();
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

	private function doQuery($query, $mode = PDO::FETCH_COLUMN){
		$obj = $this->query($query);

		if($obj){
			return $obj->fetchAll($mode);
		}

		return NULL;
	}

	public function getDatabaseNames(){
		return $this->doQuery('SHOW DATABASES');
	}

	public function getTableNames(){
		$data = [$this->dbName];
		$query = $this->prePrepare('SHOW TABLES IN $other', $data);

		return $this->doQuery($query);
	}

	public function tableExists($table){
		$tableNames = $this->getTableNames();

		if(in_array(strtolower($table), $tableNames)){
			return true;
		}

		return false;
	}

	public function getColumnNames($table){
		$data = [$table];
		$query = $this->prePrepare('DESCRIBE $table', $data);

		return $this->doQuery($query);
	}

	public function columExists($table, $column){
		$columnNames = $this->getColumnNames($table);

		if(in_array(strtolower($column), $columnNames)){
			return true;
		}

		return false;
	}

	public function prepare($query, $data = []){
		$query = $this->prePrepare($query, $data);

		$obj = parent::prepare($query);
		$this->lastQuerry = $query;

		foreach($data as $key => $val){
			$obj->bindValue($key + 1, $val);
		}

		if(!$obj->execute()){
			return $this->errorInfo();
		}

		return $obj->fetchAll(PDO::FETCH_ASSOC);
	}

	private function prePrepare($query, &$data){
		$pattern = '/(?<!\$)\$(?!\$)/';

		$numberOfMatches = preg_match_all($pattern, $query);

		for($i = 0; $i < $numberOfMatches; $i++){
			if(preg_match('/^\w+$/', $data[0])){
				$query = preg_replace($pattern, array_shift($data), $query, 1);
			} else{
				return false;
			}
		}

		str_replace('$$', '$', $query);

		return $query;
	}

}

?>
