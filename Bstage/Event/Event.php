<?php

//PSR-14 compatible (without interfaces)

namespace Bstage\Event;

class Event {

	protected $name = '';
	protected $params = [];
	protected $changes = [];
	protected $stopped = false;

	public function __construct($name, array $params=[]) {
		$this->name = $name;
		$this->params = $params;
    }

	public function __get($key) {
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}

	public function __set($key, $val) {
		$this->params[$key] = $this->changes[$key] = $val;
	}

	public function getName() {
		return $this->name;
	}

	public function getParams($key=null) {
		if($key === null) {
			return $this->params;
		}
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}

	public function setParams(array $params, $merge=false) {
		$this->params = $merge ? array_merge($this->params, $params) : $params;
	}

	public function getChanges($key=null) {
		if($key === null) {
			return $this->changes;
		}
		return isset($this->changes[$key]) ? $this->changes[$key] : null;
	}

	public function stopPropagation() {
		$this->stopped = true;
	}

	public function isPropagationStopped() {
		return $this->stopped;
	}

}