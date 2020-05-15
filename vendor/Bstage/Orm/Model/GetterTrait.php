<?php

namespace Bstage\Orm\Model;

trait GetterTrait {

	final public function __isset($key) {
		return ($key !== 'app') && property_exists($this, $key);
	}

	final public function __get($key) {
		//does property exist?
		if($key === 'app' || !property_exists($this, $key)) {
			throw new \Exception("Undefined property: $key");
		}
		//get property
		return $this->$key;
	}

}