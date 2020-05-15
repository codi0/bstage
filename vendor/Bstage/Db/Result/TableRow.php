<?php

namespace Bstage\Db\Result;

class TableRow extends \ArrayObject {

	protected $_db;
	protected $_table;
	protected $_primaryKey;
	protected $_changes = [];

	public function __construct(array $opts=[]) {
		//get data
		$data = isset($opts['data']) ? $opts['data'] : [];
		//call parent
		parent::__construct($data, \ArrayObject::ARRAY_AS_PROPS);
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, "_$k")) {
				$this->{"_$k"} = $v;
			}
		}
		//table not set?
		if($this->_db && !$this->_table) {
			throw new \Exception("Table name required");
		}
		//guess primary key?
		if(!$this->_primaryKey) {
			$this->_primaryKey = $data ? array_keys($data)[0] : 'id';
		}
	}

	public function toArray() {
		return (array) $this;
	}

	public function pkCol() {
		return $this->_primaryKey;
	}

	public function pkVal() {
		return isset($this[$this->_primaryKey]) ? $this[$this->_primaryKey] : null;
	}

	public function save(array $allowedCols=[]) {
		//any changes?
		if(!$this->_changes) {
			return true;
		}
		//set vars
		$data = [];
		$result = true;
		//sync source data?
		if($this->_db) {
			//already has ID?
			if($id = $this->pkVal()) {
				//loop through changes
				foreach($this->_changes as $col) {
					$data[$col] = $this[$col];
				}
				//get data
				$data = $this->formatData($data, $allowedCols);
				//execute update query
				$result = (bool) $this->_db->update($this->_table, $data, [ $this->_primaryKey => $id ]);
			} else {
				//get data
				$data = $this->formatData((array) $this, $allowedCols);
				//execute insert query
				$result = (bool) $this->_db->insert($this->_table, $data);
				//set primary key?
				if($result) {
					$this[$this->_primaryKey] = $this->_db->insertId();
				}
			}
		}
		//clear changes?
		if($result) {
			$this->_changes = [];
		}
		//return
		return $result;
	}

	public function delete() {
		//set vars
		$result = true;
		//delete source data?
		if($this->_db) {
			//can delete?
			if(!$id = $this->pkVal()) {
				throw new \Exception("Primary key has no value set");
			}
			//execute delete query
			$result = (bool) $this->_db->delete($this->_table, [ $this->_primaryKey => $id ]);
		}
		//clear changes?
		if($result) {
			$this->_changes = [];
		}
		//return
		return $result;
	}

	public function clear() {
		//reset changes
		$this->_changes = [];
		//clear array
		return $this->exchangeArray([]);
	}

	public function offsetSet($key, $val) {
		//mark as changed?
		if(!array_key_exists($key, $this) || $this[$key] != $val) {
			$this->_changes[] = $key;
		}
		//call parent
		return parent::offsetSet($key, $val);
	}

	public function offsetUnset($key) {
		//key exists?
		if(array_key_exists($key, $this)) {
			$this->_changes[] = $key;
		}
		//call parent
		return parent::offsetUnset($key);
	}

	protected function formatData(array $data, array $allowedCols) {
		//loop through data
		foreach($data as $col => $val) {
			//remove column?
			if($allowedCols && !in_array($col, $allowedCols)) {
				unset($data[$col]);
				continue;
			}
			//requires format?
			if(is_bool($val)) {
				$data[$col] = $val ? 1 : 0;
			} else if(is_null($val)) {
				$data[$col] = '';
			}
		}
		//return
		return $data;
	}

}