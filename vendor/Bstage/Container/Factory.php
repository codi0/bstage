<?php

namespace Bstage\Container;

class Factory {

	protected $classFormats = [];
	protected $defaultOpts = [];

	protected $cache = [];

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//class format set?
		if(!$this->classFormats) {
			throw new \Exception("Class format array required");
		}
	}

	public function __get($name) {
		return $this->create($name);
	}

	public function get($name) {
		return $this->create($name);
	}

	public function create($name, array $opts=[]) {
		//create object?
		if(!isset($this->cache[$name])) {
			//set vars
			$class = '';
			$count = count($this->classFormats) - 1;
			//loop through classes
			foreach($this->classFormats as $key => $val) {
				//format class name
				$class = str_replace('{name}', $name, $val);
				//stop here?
				if($key == $count || class_exists($class)) {
					break;
				}
			}
			//merge default opts
			$opts = array_merge($this->defaultOpts, $opts);
			//loop through opts
			foreach($opts as $key => $val) {
				//format opt?
				if(is_string($val)) {
					$opts[$key] = str_replace('{name}', $name, $val);
				}
			}
			//create object
			$this->cache[$name] = new $class($opts);
		}
		//return
		return $this->cache[$name];
	}

}