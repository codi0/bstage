<?php

namespace Bstage\Db;

class Pdo extends AbstractDb {

	protected $driver = 'mysql';

	public function getDbh() {
		//is connected?
		if(!$this->dbh) {
			try {
				//create dsn
				$dsn = $this->driver . ':host=' . $this->host . ';dbname=' . $this->name . ';charset=' . $this->charset;
				//attempt connecyion
				$this->dbh = new \PDO($dsn, $this->user, $this->pass, array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				));
			} catch(\Exception $e) {
				//connection failed
				$this->addError($e->getMessage(), 'connect');
				$this->dbh = null;
			}
		}
		//return
		return $this->dbh;
	}

	public function query($sql, array $params=array()) {
		//reset vars
		$this->insertId = 0;
		$this->rowsAffected = 0;
		//get dbh
		if(!$dbh = $this->getDbh()) {
			return null;
		}
		//format params
		$params = $this->formatParams($params);
		//format table name
		$sql = $this->formatTable($sql);
		//prepare sql
		$statement = $dbh->prepare($sql);
		//log query
		$this->queries[] = $sql;
		//run query
		try {
			$statement->execute($params);
			$this->insertId = $dbh->lastInsertId();
			$this->rowsAffected = $statement->rowCount();
		} catch(\Exception $e) {
			$this->addError($e->getMessage(), $sql);
			$statement = null;
		}
		//return
		return $statement;
	}

	public function getOne($sql, array $params=array()) {
		//set vars
		$res = null;
		//query executed?
		if($q = $this->query($sql, $params)) {
			//fetch first row?
			if($row = $q->fetch(\PDO::FETCH_NUM)) {
				$res = $row[0];
			}
		}
		//return
		return $res;
	}

	public function getRow($sql, array $params=array()) {
		//set vars
		$res = array();
		//query executed?
		if($q = $this->query($sql, $params)) {
			//fetch first row?
			if($row = $q->fetch(\PDO::FETCH_ASSOC)) {
				$res = $row;
			}
		}
		//return
		return $res;
	}

	public function getCol($sql, array $params=array()) {
		//set vars
		$res = array();
		//query executed?
		if($q = $this->query($sql, $params)) {
			//loop through rows
			while($row = $q->fetch(\PDO::FETCH_NUM)) {
				$res[] = $row[0];
			}
		}
		//return
		return $res;
	}

	public function getAll($sql, array $params=array()) {
		//set vars
		$res = array();
		//query executed?
		if($q = $this->query($sql, $params)) {
			//loop through rows
			while($row = $q->fetch(\PDO::FETCH_ASSOC)) {
				$res[] = $row;
			}
		}
		//return
		return $res;
	}

	public function close() {
		$this->dbh = null;
	}

}