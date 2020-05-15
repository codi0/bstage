<?php

namespace Bstage\Model;

trait AccessorsTrait {

	private $__a = [
		'props' => [],
		'methods' => [],
		'changes' => [],
		'calling' => [],
	];

	public function __construct(array $opts=[]) {
		//track by default
		//NB: if concrete class defines a constructor, __track must be called there manually
		$this->__track($opts);
	}

	public function __isset($key) {
		//check if property exists
		return isset($this->__a['props'][$key]);
	}

	public function __get($key) {
		//property exists?
		if(!isset($this->__a['props'][$key])) {
			throw new \Exception("Undefined property: $key");
		}
		//analyse method
		$method = 'get' . ucfirst($key);
		$methodExists = isset($this->__a['methods'][$method]);
		$methodInternal = $methodExists && !$this->__a['methods'][$method]['public'];
		//analyse caller
		$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$internalCall = isset($debug[1]) && isset($debug[1]['class']) && $debug[1]['class'] === get_class($this);
		//public call allowed?
		if(!$internalCall && !$this->__a['props'][$key]['public']) {
			if(!$methodExists || $methodInternal) {
				throw new \Exception("Trying to access private property: $key");
			}
		}
		//public call allowed?
		if(!$methodExists && !$internalCall && !$this->__a['props'][$key]['public']) {
			throw new \Exception("Trying to access private property: $key");
		}
		//mark call started
		$this->__a['calling'][$key] = true;
		$oldVal = $newVal = $this->__a['props'][$key]['val'];
		//call accessor?
		if($methodExists) {
			//create property
			$this->$key = $oldVal;
			//call method
			$newVal = $this->$method();
			//remove property
			unset($this->$key);
		}
		//mark as changed?
		if($newVal !== $oldVal) {
			$this->__a['changes'][$key] = true;
			$this->__a['props'][$key]['val'] = $newVal;
		}
		//mark call ended
		unset($this->__a['calling'][$key]);
		//return
		return $newVal;
	}

	public function __set($key, $val) {
		//is calling?
		if(isset($this->__a['calling'][$key])) {
			$this->$key = $val;
			return;
		}
		//property exists?
		if(!isset($this->__a['props'][$key])) {
			throw new \Exception("Undefined property: $key");
		}
		//analyse method
		$method = 'set' . ucfirst($key);
		$methodExists = isset($this->__a['methods'][$method]);
		$methodInternal = $methodExists && !$this->__a['methods'][$method]['public'];
		//analyse caller
		$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$internalCall = isset($debug[1]) && isset($debug[1]['class']) && $debug[1]['class'] === get_class($this);
		//public call allowed?
		if(!$internalCall && !$this->__a['props'][$key]['public']) {
			if(!$methodExists || $methodInternal) {
				throw new \Exception("Trying to access private property: $key");
			}
		}
		//anything to update?
		if($this->__a['props'][$key]['val'] == $val) {
			return;
		}
		//call accessor?
		if($methodExists) {
			//create property
			$this->$key = $this->__a['props'][$key]['val'];
			//call method
			$this->$method($val);
			//update value
			$val = $this->$key;
			//remove property
			unset($this->$key);
		}
		//mark as changed
		$this->__a['changes'][$key] = true;
		$this->__a['props'][$key]['val'] = $val;
	}

	public function __call($method, array $args) {
		//parse method
		$action = substr($method, 0, 3);
		$key = lcfirst(substr($method, 3));
		//method exists?
		if($action !== 'get' && $action !== 'set') {
			throw new \Exception("Undefined method: $method");
		}
		//property exists?
		if(!isset($this->__a['props'][$key])) {
			throw new \Exception("Undefined property: $key");
		}
		//analyse method
		$methodExists = isset($this->__a['methods'][$method]);
		$methodInternal = $methodExists && !$this->__a['methods'][$method]['public'];
		//analyse caller
		$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$internalCall = isset($debug[1]) && isset($debug[1]['class']) && $debug[1]['class'] === get_class($this);
		//public call allowed?
		if(!$internalCall && $methodExists && $methodInternal) {
			throw new \Exception("Trying to access private method: $method");
		}
		//get property?
		if($action === 'get') {
			return $this->__a['props'][$key]['val'];
		}
		//valid args?
		if(empty($args)) {
			throw new \Exception("No arguments passed to " . $method);
		}
		//value changed?
		if($this->__a['props'][$key]['val'] == $args[0]) {
			return;
		}
		//mark as changed
		$this->__a['changes'][$key] = true;
		$this->__a['props'][$key]['val'] = $args[0];
	}

	public function __track(array $opts=[]) {
		//already setup?
		if($this->__a['props']) {
			return;
		}
		//object reflection
		$reflection = new \ReflectionObject($this);
		//loop through methods
		foreach($reflection->getMethods() as $method) {
			//get name
			$name = $method->getName();
			//skip method?
			if(strpos($name, '__') === 0) {
				continue;
			}
			//cache reference
			$this->__a['methods'][$name] = [
				'public' => $method->isPublic(),
			];
		}
		//loop through properties
		foreach($reflection->getProperties() as $prop) {
			//get name
			$name = $prop->getName();
			//skip property?
			if(strpos($name, '__') === 0) {
				continue;
			}
			//cache reference
			$this->__a['props'][$name] = [
				'val' => isset($opts[$name]) ? $opts[$name] : $this->$name,
				'public' => $prop->isPublic(),
			];
			//remove property
			unset($this->$name);
		}
	}

	public function __changes($reset=false) {
		//set vars
		$changes = [];
		//loop through changes
		foreach($this->__a['changes'] as $key => $val) {
			$changes[$key] = $this->__a['props'][$key]['val'];
		}
		//reset?
		if($reset) {
			$this->__a['changes'] = [];
		}
		//return
		return $changes;
	}

}