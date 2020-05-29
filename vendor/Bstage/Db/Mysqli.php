<?php

namespace Bstage\Db;

class Mysqli extends AbstractDb {

	public function getDbh() {
		//is connected?
		if(!$this->dbh) {
			//attempt connection
			$this->dbh = mysqli_connect($this->host, $this->user, $this->pass);
			//connection error?
			if($error = mysqli_connect_error()) {
				$this->dbh = null;
				$this->addError($error, 'connect');
				return null;
			}
			//connected?
			if($this->dbh) {
				//select database
				mysqli_select_db($this->dbh, $this->name);
				//select error?
				if($error = mysqli_error($this->dbh)) {
					$this->dbh = null;
					$this->addError($error, 'select db');
				}
			}
			//set charset?
			if($this->dbh && $this->charset) {
				 mysqli_set_charset($this->dbh, $this->charset);
			}
			//set timezone?
			if($this->dbh) {
				mysqli_query($this->dbh, "SET time_zone = '" . $this->getTimeOffset() . "'");
			}
		}
		//return
		return $this->dbh;
	}

	public function escape($str) {
		if($dbh = $this->getDbh()) {
			return mysqli_real_escape_string($dbh, $str);
		} else {
			return parent::escape($str);
		}
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
		//format table?
		$sql = $sqlPrep = $this->formatTable($sql);
		//insert params
		foreach($params as $key => $val) {
			//get tokens
			if(is_numeric($key)) {
				$tokens = array( '?' );
			} else {
				$tokens = array( ':' . $key, '?' );
			}
			//escape value
			$escaped = "'" . $this->escape($val) . "'";
			//check tokens
			foreach($tokens as $token) {
				//replace token?
				if($pos = strpos($sqlPrep, $token)) {
					$sqlPrep = substr_replace($sqlPrep, $escaped, $pos, strlen($token));
					break;
				}
			}
		}
		//log query
		$this->queries[] = $sql;
		//execute query
		$res = mysqli_query($dbh, $sqlPrep);
		//has error?
		if($error = mysqli_error($dbh)) {
			$this->addError($error, $sql);
			$res = null;
		} else {
			$this->insertId = mysqli_insert_id($dbh);
			$this->rowsAffected = mysqli_affected_rows($dbh);
		}
		//return
		return $res;
	}

	public function getOne($sql, array $params=array()) {
		//set vars
		$res = null;
		//query executed?
		if($q = $this->query($sql, $params)) {
			//fetch first row?
			if($row = mysqli_fetch_row($q)) {
				$res = $row[0];
			}
			//free result
			mysqli_free_result($q);
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
			if($row = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
				$res = $row;
			}
			//free result
			mysqli_free_result($q);
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
			while($row = mysqli_fetch_row($q)) {
				$res[] = $row[0];
			}
			//free result
			mysqli_free_result($q);
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
			while($row = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
				$res[] = $row;
			}
			//free result
			mysqli_free_result($q);
		}
		//return
		return $res;
	}

	public function close() {
		if($this->dbh) {
			mysqli_close($this->dbh);
			$this->dbh = null;
		}
	}

}