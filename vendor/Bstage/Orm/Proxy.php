<?php

namespace Bstage\Orm;

class Proxy {

	protected $name;
	protected $query = [];
	protected $references = [];
	protected $autoInsert = false;

	protected $orm;
	protected $model;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//valid proxy?
		if(!$this->name || !$this->query) {
			throw new \Exception("Proxy requires a model name and query parameter");
		}
		//orm passed?
		if(!$this->orm) {
			throw new \Exception("ORM object not set");
		}
	}

	public function __isset($key) {
		return isset($this->createModel()->$key);
	}

	public function __get($key) {
		return $this->createModel()->$key;
	}

	public function __set($key, $val) {
		$this->createModel()->$key = $val;
	}

	public function __unset($key) {
		unset($this->createModel()->$key);
	}

	public function __call($method, array $args) {
		return $this->createModel()->$method(...$args);
	}

	public function addReference($model, $property) {
		//add to array
		$this->references[] = [
			'model' => $model,
			'property' => $property,
		];
		//chain it
		return $this;
	}

	protected function createModel() {
		echo 'test';
		//use cache?
		if($this->model) {
			return $this->model;
		}
		//create model?
		$this->model = $this->orm->get($this->name, [
			'query' => $this->query,
			'autoInsert' => $this->autoInsert,
		]);
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
				throw new \Exception("Property on parent object not found: " . $ref['property']);
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