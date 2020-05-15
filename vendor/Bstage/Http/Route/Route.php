<?php

namespace Bstage\Http\Route;

class Route {

	protected $name;
	protected $prefix;
	protected $params = [];

	public function __construct($name, array $params=[], $prefix='') {
		$this->name = $name;
		$this->params = $params;
		$this->prefix = $prefix;
	}

	public function getName() {
		return $this->name;
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