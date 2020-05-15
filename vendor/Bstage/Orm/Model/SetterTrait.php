<?php

namespace Bstage\Orm\Model;

trait SetterTrait {

	final public function __set($key, $val) {
		//does property exist?
		if(!property_exists($this, $key)) {
			throw new \Exception("Undefined property: $key");
		}
		//set property
		$this->$key = $val;
	}

}