<?php

namespace Bstage\Orm\Model;

trait ConstructorTrait {

	protected $app;

	public function __construct(array $opts=[]) {
		return $this->__constructRoot($opts);
	}

	final protected function __constructRoot(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

}