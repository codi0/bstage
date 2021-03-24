<?php

namespace Bstage\Orm;

class Proxy {

	protected $name = '';
	protected $model = null;
	protected $references = [];

	protected $orm = null;
	protected $ormOpts = [];

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//name set?
		if(!$this->name) {
			throw new \Exception("Proxy name required");
		}
		//orm set?
		if(!$this->orm) {
			throw new \Exception("ORM object not set");
		}
		//add reference?
		if(isset($this->ormOpts['parent']) && isset($this->ormOpts['parentProp'])) {
			$this->__reference($this->ormOpts['parent'], $this->ormOpts['parentProp']);
		}
	}

	public function __isset($key) {
		return isset($this->__create()->$key);
	}

	public function __get($key) {
		return $this->__create()->$key;
	}

	public function __set($key, $val) {
		$this->__create()->$key = $val;
	}

	public function __unset($key) {
		unset($this->__create()->$key);
	}

	public function __call($method, array $args) {
		return $this->__create()->$method(...$args);
	}

	public function __object($create=false) {
		return $create ? $this->__create() : $this->model;
	}

	public function __reference($model, $property) {
		//can set?
		if($model && $property) {
			//create hash
			$hash = spl_object_hash($model) . $property;
			//reference exists?
			if(!isset($this->references[$hash])) {
				//add reference
				$this->references[$hash] = [
					'model' => $model,
					'property' => $property,
				];
			}
		}
		//chain it
		return $this;
	}

	protected function __create() {
		//use cache?
		if($this->model) {
			return $this->model;
		}
		//create model?
		$this->model = $this->orm->get($this->name, array_merge($this->ormOpts, [
			'lazy' => false,
		]));
		//success?
		if(!$this->model) {
			throw new \Exception("Failed to create object: $this->name");
		}
		//replace proxy references
		foreach($this->references as $ref) {
			//use reflection
			$r = new \ReflectionObject($ref['model']);
			//property exists?
			if(!$r->hasProperty($ref['property'])) {
				throw new \Exception("Property does not exist on parent reference: " . $ref['property']);
			}
			//update parent property
			$p = $r->getProperty($ref['property']);
			$p->setAccessible(true);
			$p->setValue($ref['model'], $this->model);
		}
		//return
		return $this->model;
	}

}