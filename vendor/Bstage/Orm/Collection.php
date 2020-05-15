<?php

namespace Bstage\Orm;

class Collection extends \ArrayObject {

	protected $_name = '';
	protected $_query = [];
	protected $_autoInsert = false;

	protected $_orm;

	public function __construct(array $opts=array()) {
		//get data
		$data = isset($opts['models']) ? $opts['models'] : [];
		//call parent
		parent::__construct($data);
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, "_$k")) {
				$this->{"_$k"} = $v;
			}
		}
	}

	public function all() {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//return
		return (array) $this;
	}

	public function get($key) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//does model exist?
		if(!isset($this[$key])) {
			throw new \Exception("Model not found: $key");
		}
		//return
		return $this[$key];
	}

	public function add($model) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//attach model?
		if($this->_orm) {
			$this->_orm->attach($model);
		}
		//add to array
		$this[] = $model;
		//return
		return true;
	}

	public function remove($model) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//get model key
		$key = array_search($model, (array) $this);
		//key found?
		if($key !== false) {
			//remove from array
			unset($this[$key]);
			//delete model?
			if($this->_orm) {
				$this->_orm->delete($model);
			}
		}
		//return
		return true;
	}

	public function save() {
		//manager set?
		if(!$this->_orm) {
			throw new \Exception("ORM not found");
		}
		//loop through models
		foreach($this as $model) {
			$this->_orm->save($model);
		}
	}

	public function offsetIsset($key) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetIsset($key);
	}

	public function offsetGet($key) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetGet($key);
	}

	public function offsetSet($key, $val) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetSet($key, $val);
	}

	public function offsetUnset($key) {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetUnset($key);
	}

	public function getIterator() {
		//lazy load?
		if($this->_query) {
			$this->lazyLoad();
		}
		//call parent
		return parent::getIterator();
	}

	protected function lazyLoad() {
		//should load?
		if($this->_orm && $this->_name && $this->_query) {
			//get models as array
			$models = $this->_orm->get($this->_name, [
				'query' => $this->_query,
				'collection' => true,
				'collectionClass' => null,
				'autoInsert' => $this->_autoInsert,
			]);
			//store models
			foreach($models as $m) {
				$this[] = $m;
			}
		}
		//reset query
		$this->_query = [];
	}

}