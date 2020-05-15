<?php

namespace Bstage\Model;

abstract class AbstractModel implements \Iterator {

	protected $_data = [];
	protected $_errors = [];
	protected $_changed = [];

	protected $_table = '';
	protected $_app = null;

	public function __construct(array $opts=[]) {
		//Hook: consstructing object
		$opts = $this->__onConstruct($opts);
		//set vars
		$hydrate = [];
		//set properties
		foreach($opts as $k => $v) {
			if($k === '_data') {
				$hydrate = $v;
			} elseif(property_exists($this, $k) && !$this->$k) {
				$this->$k = $v;
			}
		}
		//merge data
		foreach(class_parents($this) as $parent) {
			$props = get_class_vars($parent);
			$this->_data = array_merge($props['_data'], $this->_data);
		}
		//set defaults
		foreach($this->_data as $k => $v) {
			//merge property
			$this->_data[$k] = array_merge([
				'value' => null,
				'save' => true,
				'readable' => true,
				'writable' => true,
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
			$this->_table = $this->_app->models->formatName($this, 'underscore');
		}
		//hydrate model
		$this->hydrate($hydrate);
	}

	public function __toString() {
		return (string) $this->id;
	}

	public function __isset($key) {
		return $key === 'id' || array_key_exists($key, $this->_data);
	}

	public function __get($key) {
		//is objKey?
		if($key === '__key') {
			return $this->id ? $this->_table . $this->id : null;
		}
		//is ID?
		if($key === 'id') {
			return $this->_data ? array_values($this->_data)[0]['value'] : null;
		}
		//property exists?
		if(!array_key_exists($key, $this->_data)) {
			throw new \Exception("Property $key does not exist");
		}
		//is readable?
		if(!$this->_data[$key]['readable']) {
			return null;
		}
		//is relation?
		if($this->_data[$key]['relation']) {
			$this->_data[$key]['value'] = $this->queryRelation($key);
		}
		//Hook: getting property
		return $this->__onGet($key, $this->_data[$key]['value']);
	}

	public function __set($key, $val) {
		//property exists?
		if(!array_key_exists($key, $this->_data)) {
			throw new \Exception("Property $key does not exist");
		}
		//is writable?
		if(!$this->_data[$key]['writable']) {
			throw new \Exception("Property $key is not writable");
		}
		//is relation ID?
		if($this->_data[$key]['relation'] && is_scalar($val)) {
			$val = $this->queryRelation($key, $val);
		}
		//has value changed?
		if($val == $this->_data[$key]['value']) {
			return;
		}
		//Hook: setting property
		$val = $this->__onSet($key, $val);
		//mark changed?
		if(!$this->_data[$key]['relation'] && !in_array($key, $this->_changed)) {
			$this->_changed[] = $key;
		}
		//update value
		$this->_data[$key]['value'] = $val;
	}

	public function toArray(array $opts=[]) {
		//set vars
		$res = [];
		//format opts
		$opts = array_merge([
			'key' => 'value',
			'underscore' => false
		], $opts);
		//loop through data
		foreach($this->_data as $name => $meta) {
			//format name?
			if($opts['underscore']) {
				$name = $this->_app->models->formatName($name, 'underscore');
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
		//Hook: validating model
		$this->__onValidate(!$id);
		//loop through values
		foreach($this->_data as $key => $val) {
			//should change?
			if(!$id && $val['value']) {
				$this->_changed[] = $key;
			}
			//validate data?
			if($this->_data[$key]['validate'] && in_array($key, $this->_changed)) {
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
			if(!array_key_exists($key, $this->_data) || !$this->_data[$key]['save']) {
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
			//set vars
			$dataDb = [];
			//Hook: saving model
			$data = $this->__onSave($data, !$id);
			//has errors?
			if($this->_errors) {
				return false;
			}
			//final checks on data
			foreach($this->_data as $key => $val) {
				//set foreign key relation?
				if(!$id && $val['relation'] === 'belongsTo') {
					//format column name
					$colName = $this->_app->models->formatName($key, 'underscore');
					//add to DB data
					$dataDb[$colName . '_id'] = $this->$key->id;
				}
				//matches save hook?
				if(array_key_exists($key, $data)) {
					//update value
					$this->_data[$key]['value'] = $data[$key];
					//format column name
					$colName = $this->_app->models->formatName($key, 'underscore');
					//add to DB data
					$dataDb[$colName] = $data[$key];
				}
			}
			//model exists?
			if(!empty($id)) {
				//format ID column name
				$idCol = $this->_app->models->formatName($idProp, 'underscore');
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
			$idCol = $this->_app->models->formatName($idProp, 'underscore');
			//delete from database
			$this->_app->db->delete($this->_table, [ $idCol => $id ]);
			//remove ID
			$this->_data[$idProp]['value'] = null;
			//reset changes
			$this->_errors = [];
			$this->_changed = [];
		}
		//return
		return true;
	}

	/* INTERNAL METHODS */

	protected function hydrate($data, $changed=false) {
		//Hook: hydrating model
		$data = $this->__onHydrate($data);
		//set vars
		$keys = [];
		$idProp = array_keys($this->_data)[0];
		$hasId = isset($data[$idProp]) && $data[$idProp];	
		//loop through data
		foreach($data as $key => $val) {
			//format key
			$tmp = $this->_app->models->formatName($key, 'camelcase');
			//key exists?
			if(!array_key_exists($tmp, $this->_data)) {
				//test for relation
				$tmp = str_replace('_id', '', $key);
				//relation found?
				if(isset($this->_data[$tmp]) && isset($this->_data[$tmp]['relation'])) {
					$this->_data[$tmp]['id'] = $val;
				}
				//skip
				continue;
			}
			//update key
			$key = $tmp;
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
			//has changed?
			if($changed) {
				//Hook: setting property
				$val = $this->__onSet($key, $val);
			}
			//set data
			$this->_data[$key]['value'] = $val;
			//add key
			$keys[] = $key;
			//mark as changed?
			if(($changed || !$hasId) && !$this->_data[$key]['relation']) {
				if(!in_array($key, $this->_changed)) {
					$this->_changed[] = $key;
				}
			}
		}
		//save now?
		if($keys && !$hasId) {
			$this->save();
		}
		//Hook: model setup
		$this->__onReady();
		//success
		return true;
	}

	protected function queryRelation($key, $val=null) {
		//create relation?
		if($this->_data[$key]['relation'] && !$this->_data[$key]['value']) {
			//set vars
			$query = [];
			//query by foreign key?
			if($this->_data[$key]['relation'] === 'belongsTo') {
				if($val || (isset($this->_data[$key]['id']) && $this->_data[$key]['id'])) {
					$query['id'] = $val ?: $this->_data[$key]['id'];
				}
			} else {
				if($val || $this->id) {
					$query[$this->_table . '_id'] = $val ?: $this->id;
				}
			}
			//query by key
			$this->_data[$key]['value'] = $this->_app->models->get($key, [
				'collection' => stripos($this->_data[$key]['relation'], 'many') !== false,
				'query' => $query,
				'relations' => [ $this ],
			]);
		}
		//return
		return $this->_data[$key]['value'];
	}

	protected function addError($message, $field='') {
		//add error
		$this->_errors = $this->_app->validator->addError($message, $field);
		//return
		return $this->_errors;
	}

	protected function isValid($value, $rules, array $opts=[], $store=true) {
		//run validator
		if(!$isValid = $this->_app->validator->isValid($value, $rules, $opts) && $store) {
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

	protected function __onConstruct(array $opts) {
		return $opts;
	}

	protected function __onHydrate($data) {
		return $data;
	}

	protected function __onReady() {
		return;
	}

	protected function __onValidate($isNew) {
		return;
	}

	protected function __onSave(array $data, $isNew) {
		return $data;
	}

	protected function __onGet($key, $val) {
		return $val;
	}

	protected function __onSet($key, $val) {
		return $val;
	}

}