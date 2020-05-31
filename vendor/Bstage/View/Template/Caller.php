<?php

namespace Bstage\View\Template;

class Caller implements \ArrayAccess {

	protected $prefix = '';
	protected $engine = null;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//engine set?
		if(!$this->engine) {
			throw new \Exception("Template engine not set");
		}
		//format prefix?
		if($this->prefix) {
			$this->prefix = trim($this->prefix, '.') . '.';
		}
	}

	public function __toString() {
		return $this->parseExpr('');
	}

	public function __call($method, array $args=[]) {
		return $this->engine->call($method, $args);	
	}

	public function offsetExists($key) {
		return $this->engine->getData($this->prefix . $key, null) !== null;
	}

    public function offsetGet($key) {
		return $this->parseExpr($key);
	}

	public function offsetSet($key, $val) {
		throw new \Exception("Template data is read only");
	}

	public function offsetUnset($key) {
		throw new \Exception("Template data is read only");
	}

	protected function parseExpr($key) {
		//set vars
		$tpl = $this;
		$val = null;
		$escaped = false;
		//format key
		$key = preg_replace_callback('/\$([a-z0-9\-\_\.]+)/i', function($match) use($tpl) {
			return $tpl->parseExpr($match[1]);
		}, $key);
		//parse callbacks
		$callbacks = array_map('trim', explode('|', $key));
		//is function call?
		if(preg_match('/^(\w+)\(([^\)]+)?\)$/', $callbacks[0], $match)) {
			//could be class method?
			if(!isset($match[2]) || !$match[2]) {
				//test data
				$method = $match[1];
				$tmp = $this->engine->getData(trim($this->prefix, '.'));
				//is class method?
				if(is_object($tmp) && method_exists($tmp, $method)) {
					$key = array_shift($callbacks);
					$val = $tmp->$method();
				}
			}
		} else {
			//check data store
			$key = array_shift($callbacks);
			$val = $this->engine->getData(trim($this->prefix . $key, '.'), '');
		}
		//actions to array?
		if(is_string($callbacks)) {
			$callbacks = $callbacks ? explode('|', $callbacks) : [];
		}
		//loop through callbacks
		foreach($callbacks as $cb) {
			//set vars
			$params = [];
			$cb = trim($cb);
			//escape rule?
			if(strpos($cb, 'esc') === 0) {
				//use escaper
				$escaped = true;
				$rule = lcfirst(substr($cb, 3));
				$val = $this->engine->call('esc', [ $val, $rule ]);
			} else {
				//function call?
				if(preg_match('/^(\w+)\(([^\)]+)?\)$/', $cb, $match)) {
					//update action
					$params = [];
					$cb = $match[1];
					//get params
					if(isset($match[2]) && preg_match_all("/'[^']*'|[^,]+/", $match[2], $m)) {
						if($m) {
							$params = array_map(function($i) {
								return trim(trim($i), "'");
							}, array_shift($m));
						}
					}
				}
				//add to params?
				if($val !== null) {
					array_unshift($params, $val);
				}
				//execute callback?
				if($cb === 'raw') {
					$escaped = true;
				} else {
					$val = $this->engine->call($cb, $params);
				}
				
			}
		}
		//auto escape string?
		if(!$escaped && is_string($val)) {
			$val = $this->engine->call('esc', [ $val, 'html' ]);
		}
		//wrap result?
		if(is_array($val) || ($val instanceof \ArrayAccess)) {
			//tmp array
			$tmp = [];
			//loop through array
			foreach($val as $k => $v) {
				$tmp[$k] = new self([
					'prefix' => trim($this->prefix . $key, '.') . '.' . $k,
					'engine' => $this->engine,
				]);
			}
			//update value
			$val = $tmp;
		}
		//return
		return $val;
	}

}