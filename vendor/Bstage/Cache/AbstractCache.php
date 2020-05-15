<?php

//PSR-16 compatible (without interfaces)

namespace Bstage\Cache;

abstract class AbstractCache {

	protected $autoGc = 0;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//auto garbage collection?
		if($this->autoGc > 0 && mt_rand(1, $this->autoGc) == 1) {
			$this->gc();
		}
	}

	abstract public function has($key);

	abstract public function get($key, $default=null);

	public function getMultiple($keys, $default=null) {
		$res = array();
		foreach($keys as $key) {
			$res[$key] = $this->get($key, $default);
		}
		return $res;
	}

	abstract public function set($key, $value, $ttl=null);

	public function setMultiple($values, $ttl=null) {
		foreach($values as $key => $value) {
			$this->set($key, $value, $ttl);
		}
		return true;
	}

	abstract public function delete($key);

	public function deleteMultiple($keys) {
		foreach($keys as $key) {
			$this->delete($key);
		}
		return true;	
	}

	abstract public function clear();

	abstract public function gc();
	
}