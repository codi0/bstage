<?php

namespace Bstage\Route;

class Route {

	protected $name;
	protected $params = [];

	public function __construct($name, array $params=[]) {
		$this->name = $name;
		$this->params = $params;
	}

	public function getName() {
		return $this->name;
	}

	public function getParams() {
		return $this->params;
	}

	public function getParam($name, $default=null) {
		return isset($this->params[$name]) ? $this->params[$name] : $default;
	}

}