<?php

class Sqlhandle extends PDO {

	private $host;
	private $dbName;
	private $user;
	private $password;
	public $lastQuerry = '';

	public function __construct($host = 'localhost', $dbName = '', $user = 'root', $password = '') {
		$this->host = $host;
		$this->dbName = $dbName;
		$this->user = $user;
		$this->password = $password;

		$this->conn();
	}

	public function setDbName($dbName) {
		$this->dbName = $dbName;
		$this->conn();
	}

	private function conn() {
		try {
			parent::__construct(
					'mysql:host=' . $this->host . ';dbname=' . $this->dbName . ';', $this->user, $this->password, array(PDO::ATTR_TIMEOUT => 1)
			);
		} catch (PDOException $exception) {
			return $exception;
		}

		return true;
	}

	private function doQuery($query, $mode = PDO::FETCH_COLUMN) {
		$obj = $this->query($query);

		if($obj) {
			return $obj->fetchAll($mode);
		}

		return NULL;
	}

	public function getDatabaseNames() {
		return $this->doQuery('SHOW DATABASES');
	}

	public function getTableNames() {
		$data = ['others' => [$this->dbName]];
		$query = $this->prepareQuery('SHOW TABLES IN $other', $data);

		return $this->doQuery($query);
	}

	public function tableExists($table) {
		$tableNames = $this->getTableNames();

		if(in_array(strtolower($table), $tableNames)) {
			return true;
		}

		return false;
	}

	public function getColumnNames($table) {
		$data = ['tables' => [$table]];
		$query = $this->prepareQuery('DESCRIBE $table', $data);

		return $this->doQuery($query);
	}

	public function columExists($table, $column) {
		$columnNames = $this->getColumnNames($table);

		if(in_array(strtolower($column), $columnNames)) {
			return true;
		}

		return false;
	}

	public function prepare($query, $data = []) {
		if(!$this->isNumeric($data)) {
			$query = $this->prepareQuery($query, $data);
		}

		$obj = parent::prepare($query);
		$this->lastQuerry = $query;

		foreach($data as $key => $val) {
			$obj->bindValue($key + 1, $val);
		}

		if(!$obj->execute()) {
			return NULL;
		}

		return $obj->fetchAll(PDO::FETCH_ASSOC);
	}

	private function isNumeric($data) {
		return array_keys($arr) === range(0, count($arr) - 1);
	}

	private function prepareQuery($query, &$data) {
		if(isset($data['columns'])) {
			$columnNames = array();

			foreach($data['tables'] as $table) {
				$columnNames = array_merge($columnNames, $this->getColumnNames($table));
			}

			$query = $this->prepareField('columns', $query, $data, $columnNames);
		}

		if(isset($data['tables'])) {
			$query = $this->prepareField('tables', $query, $data);
		}

		if(isset($data['others'])) {
			$query = $this->prepareField('others', $query, $data);
		}

		return $query;
	}

	private function prepareField($index, $query, &$data, $columnNames = []) {
		foreach($data[$index] as $element) {
			if($index == 'tables' and $this->tableExists($element)) {
				$query = preg_replace('/(\$table)/', $element, $query, 1);
			} else if($index == 'columns' and in_array(strtolower($element), $columnNames)) {
				$query = preg_replace('/(\$column)/', $element, $query, 1);
			} else if($index == 'others' and preg_match('/^(\w+)$/', $element)) {
				$query = preg_replace('/(\$other)/', $element, $query, 1);
			}
		}

		unset($data[$index]);

		return $query;
	}

}

?>
