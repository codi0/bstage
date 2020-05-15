<?php

namespace Bstage\Orm\Model;

trait IdTrait {

	protected $id = 0;
	protected $__pk = 'id';

	final public function __toString() {
		return (string) $this->{$this->__pk};
	}

	final public function id() {
		return $this->{$this->__pk};
	}


}