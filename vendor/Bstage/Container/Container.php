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
		//dependency registered?
		if(!isset($this->instances[$key])) {
			throw new \Exception("Dependency $key not registered");
		}
		//dependency requires autowiring?
		if($this->instances[$key] instanceof \Closure || is_string($this->instances[$key])) {
			$this->instances[$key] = $this->autowire($key, $this->instances[$key]);
		}
		//set config object?
		if(!$this->config && $key === 'config') {
			$this->config = $this->instances[$key];
		}
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

	protected function autowire($key, $entry) {
		//set vars
		$params = [];
		$isOpts = false;
		//check config?
		if($this->config) {
			//get config params
			$confKey = str_replace('{key}', $key, $this->configKey);
			$params = (array) $this->config->get($confKey, []);
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