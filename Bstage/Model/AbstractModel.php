<?php

namespace Bstage\Model;

abstract class AbstractModel implements \Iterator {

	protected $_data = [];
	protected $_table = '';

	protected $_changed = [];
	protected $_errors = [];

	protected $_app = null;

	public function __construct(array $opts=[]) {
		//set vars
		$hydrate = [];
		$parentProps = get_class_vars(get_parent_class($this));
		//set properties
		foreach($opts as $k => $v) {
			if($k === '_data') {
				$hydrate = $v;
			} elseif(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//merge data
		$this->_data = array_merge($parentProps['_data'], $this->_data);
		//check data
		foreach($this->_data as $k => $v) {
			//add defaults
			$this->_data[$k] = array_merge([
				'value' => null,
				'readonly' => false,
				'relation' => '',
				'validate' => '',
				'sanitize' => '',
				'format' => '',
			], $v);
		}
		//app not set?
		if(!$this->_app) {
			throw new \Exception('App object not passed to model');
		}
		//guess table?
		if(!$this->_table) {
			$this->_table = $this->_app->models->name($this, [ 'underscore' ]);
		}
		//hydrate model
		$this->hydrate([ 'data' => $hydrate, 'changed' => false ]);
	}

	public function __destruct() {
		$this->save();
	}

	public function __isset($key) {
		return $key === 'id' || array_key_exists($key, $this->_data);
	}

	public function __get($key) {
		//is ID?
		if($key === 'id') {
			return $this->_data ? array_values($this->_data)[0]['value'] : null;
		}
		//property exists?
		if(!array_key_exists($key, $this->_data)) {
			throw new \Exception("Property $key does not exist");
		}
		//create relation?
		if($this->_data[$key]['relation'] && !$this->_data[$key]['value']) {
			//query by key
			$this->_data[$key]['value'] = $this->_app->models->get($key, [
				'collection' => stripos($this->_data[$key]['relation'], 'many') !== false,
				'query' => [ $this->_table . '_id' => $this->id ],
				'relations' => [ $this ],
			]);
		}
		//get value
		return $this->_data[$key]['value'];
	}

	public function __set($key, $val) {
		//property exists?
		if(!array_key_exists($key, $this->_data)) {
			throw new \Exception("Property $key does not exist");
		}
		//read only property?
		if($this->_data[$key]['readonly']) {
			throw new \Exception("Property $key is read only");
		}
		//has changed?
		if($val === $this->_data[$key]['value']) {
			return;
		}
		//mark changed?
		if(!$this->_data[$key]['relation'] && !in_array($key, $this->_changed)) {
			$this->_changed[] = $key;
		}
		//set value
		$this->_data[$key]['value'] = $this->__onSet($key, $val);
	}

	public function toArray(array $opts=[]) {
		//set vars
		$res = [];
		$opts = array_merge([ 'key' => 'value', 'underscore' => false ], $opts);
		//loop through data
		foreach($this->_data as $name => $meta) {
			//format name?
			if($opts['underscore']) {
				$name = $this->_app->models->name($name, [ 'underscore' ]);
			}
			///set data
			if($opts['key']) {
				$res[$name] = isset($meta[$opts['key']]) ? $meta[$opts['key']] : null;
			} else {
				$res[$name] = $meta;
			}
		}
		//return
		return $res;
	}

	public function errors() {
		return $this->_errors;
	}

	public function changed() {
		return $this->_changed;
	}

	public function save() {
		//has changed?
		if(!$this->_changed) {
			return $this->id;
		}
		//set vars
		$data = [];
		$id = $this->id;
		$isValid = true;
		$idProp = array_keys($this->_data)[0];
		//reset errors
		$this->_errors = [];
		//loop through values
		foreach($this->_data as $key => $val) {
			//should change?
			if(!$id && $val['value']) {
				$this->_changed[] = $key;
			}
			//validate data?
			if($this->_data[$key]['validate']) {
				$this->isValid($val['value'], $this->_data[$key]['validate'], [ 'field' => $key ]);
			}
		}
		//stop here?
		if($this->_errors) {
			return false;
		}
		//loop through changes
		foreach(array_unique($this->_changed) as $key) {
			//valid data?
			if(!array_key_exists($key, $this->_data)) {
				continue;
			}
			//set val
			$val = $this->_data[$key]['value'];
			//is bool?
			if(is_bool($val)) {
				$val = $val ? '1' : '0';
			}
			//is null?
			if(is_null($val)) {
				$val = '';
			}
			//use formatter?
			if($format = $this->_data[$key]['format']) {
				if($format === 'concat') {
					$val = implode(';;', (array) $val);
				} else if($format === 'json') {
					$val = json_encode($val);
				} else {
					$val = $format($val, true);
				}
			}
			//can save?
			if(!is_scalar($val)) {
				continue;
			}
			//sanitize input?
			if($this->_data[$key]['sanitize']) {
				$val = $this->_app->validator->sanitize($val, $this->_data[$key]['sanitize']);
			}
			//add to data
			$data[$key] = $val;
		}
		//run query?
		if(!empty($data)) {
			//save hook
			$dataDb = [];
			$data = $this->__onSave($data);
			//has errors?
			if($this->_errors) {
				return false;
			}
			//final checks on data
			foreach($this->_data as $key => $val) {
				//singular relation?
				if($val['relation'] === 'hasOne') {
					//format column name
					$colName = $this->_app->models->name($key, [ 'underscore' ]);
					//add to DB data
					$dataDb[$colName . '_id'] = $this->$key->id;
				}
				//matches save hook?
				if(array_key_exists($key, $data)) {
					//update value
					$this->_data[$key]['value'] = $data[$key];
					//format column name
					$colName = $this->_app->models->name($key, [ 'underscore' ]);
					//add to DB data
					$dataDb[$colName] = $data[$key];
				}
			}
			//model exists?
			if(!empty($id)) {
				//format ID column name
				$idCol = $this->_app->models->name($idProp, [ 'underscore' ]);
				//update query
				$this->_app->db->update($this->_table, $dataDb, [ $idCol => $id ]);
			} else {
				//insert query
				$this->_data[$idProp]['value'] = $this->_app->db->insert($this->_table, $dataDb);
			}
		}
		//reset changed
		$this->_changed = [];
		//return
		return $this->_data[$idProp]['value'];	
	}

	public function delete() {
		//run query?
		if($id = $this->id) {
			//get ID column
			$idProp = array_keys($this->_data)[0];
			$idCol = $this->_app->models->name($idProp, [ 'underscore' ]);
			//delete from database
			$this->_app->db->delete($this->_table, [ $idCol => $id ]);
			//remove ID
			$this->_data[$idProp]['value'] = null;
			//reset changes
			$this->reset();
		}
		//return
		return true;
	}

	public function reset() {
		//reset changes
		$this->_errors = [];
		$this->_changed = [];
		//return
		return true;
	}

	protected function hydrate(array $opts=[]) {
		//set opts
		$opts = array_merge([
			'data' => [],
			'query' => [],
			'changed' => false,
		], $opts);
		//query data?
		if(!$opts['data'] && $opts['query']) {
			$opts['data'] = (array) $this->_app->db->select($this->_table, $opts['query'], 1);
		}
		//set vars
		$keys = [];
		//loop through data
		foreach($opts['data'] as $key => $val) {
			//has ID?
			if(!isset($hasId)) {
				$idProp = array_keys($this->_data)[0];
				$hasId = isset($opts['data'][$idProp]) && $opts['data'][$idProp];			
			}
			//format key
			$key = $this->_app->models->name($key, [ 'camelcase' ]);
			//key exists?
			if(!array_key_exists($key, $this->_data)) {
				continue;
			}
			//use formatter?
			if($format = $this->_data[$key]['format']) {
				if($format === 'concat') {
					$val = is_string($val) ? ($val ? explode(';;', $val) : []) : $val;
				} else if($format === 'json') {
					$val = is_string($val) ? json_decode($val, true) : $val;
				} else {
					$val = $format($val, false);
				}
			}
			//set data
			$this->_data[$key]['value'] = $val;
			//add key
			$keys[] = $key;
			//mark as changed?
			if(($opts['changed'] || !$hasId) && !$this->_data[$key]['relation']) {
				if(!in_array($key, $this->_changed)) {
					$this->_changed[] = $key;
				}
			}
		}
		//hydrate hook
		$this->__onHydrate($keys);
		//save now?
		if($keys && !$hasId) {
			$this->save();
		}
		//success
		return true;
	}

	protected function isValid($value, $rules, array $opts=[]) {
		//run validator
		if(!$isValid = $this->_app->validator->isValid($value, $rules, $opts)) {
			$this->_errors = $this->_app->validator->mergeErrors($this->_errors);
		}
		//return
		return $isValid;
	}

	/* ITERATOR METHODS */

	public function key() {
		return key($this->_data);
	}

	public function current() {
		return current($this->_data);
	}

	public function valid() {
		return key($this->_data) !== null;
	}

	public function rewind() {
		reset($this->_data);
	}

	public function next() {
		next($this->_data);
	}

	/* HOOK METHODS */

	protected function __onHydrate(array $keys) {
		return $keys;
	}

	protected function __onSet($key, $val) {
		return $val;
	}

	protected function __onSave(array $data) {
		return $data;
	}

}