<?php
/**
 * Version 0.0.1
 */
class Sqlhandle {

	private $host;
	private $user;
	private $dbName;
	private $pass;
	public $pdoObj;

	public function __construct($bdName, $user, $pass, $host) {
		$this->host = $host;
		$this->user = $user;
		$this->dbName = $bdName;
		$this->pass = $pass;
		$this->pdoObj = $this->conn();
	}

	public function conn() {
		$obj = '';
		try {
			$obj = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbName . ";", $this->user, $this->pass, array(PDO::ATTR_TIMEOUT => 1));
		} catch (PDOException $ex) {
			$obj = $ex;
		}
		return $obj;
	}

	public function get_dbNames() {
		$obj = $this->pdoObj->query('SHOW DATABASES');
		$res = $obj->fetchAll(PDO::FETCH_COLUMN);
		return $res;
	}

	public function getTables() {
		$obj = $this->pdoObj->query("Show Tables In $this->dbName");
		$res = $obj->fetchAll(PDO::FETCH_COLUMN);
		return $res;
	}

	public function getHeader($table) {
		$obj = $this->pdoObj->query('DESCRIBE ' . $table);
		$res = $obj->fetchAll(PDO::FETCH_COLUMN);
		return $res;
	}

	public function getTableData($tbname) {
		try {
			$obj = $this->pdoObj->query("SELECT * FROM $tbname");
			$obj->execute();
			$res = $obj->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $ex) {
			$res = false;
		}
		return $res;
	}

	public function prepare($sql, $data = []) {
		$sth = $this->pdoObj->prepare($sql);

		foreach($data as $key => $val) {
			$sth->bindValue($key + 1, $data[$key]);
		}

		if(!$sth->execute()) {
			return false;
		}
		$res = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $res;
	}

	private function do_query($string) {

		$sth = $this->pdoObj->prepare($string);
		if(!$sth->execute()) {
			return false;
		}
		$res = $sth->fetchAll(PDO::FETCH_ASSOC);
		return $res;
	}

}

?>
