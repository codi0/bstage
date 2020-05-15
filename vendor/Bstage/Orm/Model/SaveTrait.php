<?php

namespace Bstage\Orm\Model;

trait SaveTrait {

	protected $app;

	final public function errors() {
		return $this->app->orm->errors($this);
	}

	final public function save() {
		return $this->app->orm->save($this);
	}

	final public function delete() {
		return $this->app->orm->delete($this);
	}

}