<?php

namespace Bstage\Route;

class Route {

	protected $name;
	protected $prefix;
	protected $params = [];
	protected $methods = [];
	protected $callback = null;

	public function __construct(array $opts=[]) {
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function getName() {
		return $this->name;
	}

	public function getMethods() {
		return $this->methods;
	}

	public function getCallback() {
		return $this->callback;
	}

	public function getPrefix() {
		return $this->prefix;
	}

	public function getParams() {
		return $this->params;
	}

	public function getParam($name, $default=null) {
		return isset($this->params[$name]) ? $this->params[$name] : $default;
	}

}