<?php

//PSR-11 compatible (without interfaces)

namespace Bstage\Container;

class Container {

	protected $instances = [];

	protected $config;
	protected $configKey = '{key}';

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function has($key) {
		return isset($this->instances[$key]);
	}

	public function get($key) {
		//create object
		$this->instances[$key] = $this->create($key);
		//return
		return $this->instances[$key];
	}

	public function set($key, $val) {
		//set value
		$this->instances[$key] = $val;
		//return
		return true;
	}

	public function delete($key) {
		//does key exist?
		if(isset($this->instances[$key])) {
			unset($this->instances[$key]);
		}
		//return
		return true;
	}

	public function create($key, array $params=[]) {
		//dependency registered?
		if(!isset($this->instances[$key])) {
			throw new \Exception("Dependency $key not registered");
		}
		//create object?
		if(is_object($this->instances[$key])) {
			$obj = $this->instances[$key];
		} else {
			$obj = $this->autowire($key, $this->instances[$key], $params);
		}
		//set config object?
		if(!$this->config && $key === 'config') {
			$this->config = $obj;
		}
		//return
		return $obj;
	}

	protected function autowire($key, $entry, array $params=[]) {
		//set vars
		$isOpts = false;
		//check config?
		if($this->config) {
			//get config data
			$confKey = str_replace('{key}', $key, $this->configKey);
			$confData = (array) $this->config->get($confKey) ?: [];
			//merge into params
			$params = array_merge($confData, $params);
			//options key found?
			if(isset($params['opts']) && is_array($params['opts'])) {
				$params = $params['opts'];
				$isOpts = true;
			}
			//check for dependencies
			foreach($params as $k => $v) {
				if(is_string($v)) {
					$tmp = trim($v, '[]');
					if($v !== $tmp) {
						$params[$k] = $this->get($tmp);
					}
				}
			}
		}
		//wrap params?
		if($isOpts) {
			$params = [ $params ];
		}
		//is closure or function?
		if($entry instanceof \Closure || function_exists($entry)) {
			return $entry(...$params);
		}
		//class found?
		if(!class_exists($entry)) {
			throw new \Exception("Dependency class $entry not found");
		}
		//create object
		return new $entry(...$params);
	}

}