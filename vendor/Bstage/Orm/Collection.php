<?php

namespace Bstage\Orm;

class Collection extends \ArrayObject {

	protected $_name = '';
	protected $_inject = [];
	protected $_dirty = false;

	protected $_orm = null;
	protected $_ormOpts = [];

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, "_$k")) {
				$this->{"_$k"} = $v;
			}
		}
		//name set?
		if(!$this->_name) {
			throw new \Exception("Collection name required");
		}
		//orm set?
		if(!$this->_orm) {
			throw new \Exception("ORM object not set");
		}
		//get data
		$data = isset($opts['models']) ? $opts['models'] : [];
		//call parent
		parent::__construct($data);
		//clear options?
		if(!empty($data)) {
			$this->_ormOpts = [];
		}
	}

	public function name() {
		return $this->_name;
	}

	public function all() {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//return
		return (array) $this;
	}

	public function get($key) {
		//lazy load?
		if($this->_ormOpts) {
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
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//add to array
		$this[] = $this->createModel($model);
		//return
		return true;
	}

	public function remove($model) {
		//lazy load?
		if($this->_ormOpts) {
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

	public function inject($data) {
		//save for later
		foreach($data as $prop => $value) {
			$this->_inject[$prop] = $value;
		}
		//inject now?
		if(!$this->_ormOpts) {
			//loop through models
			foreach($this as $model) {
				$this->_orm->mapper($model)->inject($data);
			}
		}
	}

	public function save(array $hashes=[]) {
		//should save?
		if(!$this->_ormOpts) {
			//mark as not dirty
			$this->_dirty = false;
			//loop through models
			foreach($this as $model) {
				$this->_orm->save($model, $hashes);
			}
		}
		//return
		return true;
	}

	public function delete(array $hashes=[]) {
		//manager set?
		if(!$this->_orm) {
			throw new \Exception("ORM not found");
		}
		//mark as not dirty
		$this->_dirty = false;
		//loop through models
		foreach($this as $model) {
			$this->_orm->delete($model, $hashes);
		}
		//return
		return true;
	}

	public function dirty() {
		return $this->_dirty;
	}

	public function offsetIsset($key) {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetIsset($key);
	}

	public function offsetGet($key) {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetGet($key);
	}

	public function offsetSet($key, $val) {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//create model
		$val = $this->createModel($val);
		//call parent
		return parent::offsetSet($key, $val);
	}

	public function offsetUnset($key) {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//call parent
		return parent::offsetUnset($key);
	}

	public function getIterator() {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//call parent
		return parent::getIterator();
	}

	public function count() {
		//lazy load?
		if($this->_ormOpts) {
			$this->lazyLoad();
		}
		//call parent
		return parent::count();
	}

	protected function lazyLoad() {
		//stop here?
		if(!$this->_ormOpts) {
			return;
		}
		//clear opts
		$this->_ormOpts = [];
		//get collection as array
		$models = $this->_orm->get($this->_name, array_merge($this->_ormOpts, [
			'lazy' => false,
			'collection' => true,
			'collectionClass' => null,
		]));
		//loop through models
		foreach($models as $m) {
			$this[] = $this->createModel($m);
		}
	}

	protected function createModel($model) {
		//create model?
		if(is_array($model)) {
			$model = $this->_orm->get($this->_name, [
				'data' => $model,
			]);
		}
		//valid model?
		if(!is_object($model)) {
			throw new \Exception("Unable to create model");
		}
		//mark as dirty
		$this->_dirty = true;
		//inject data?
		if($this->_inject) {
			$this->_orm->mapper($model)->inject($this->_inject);
		}
		//return
		return $model;
	}

}