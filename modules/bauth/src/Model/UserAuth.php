<?php

namespace Bauth\Model;

class UserAuth extends User {

	use UserAuthTrait;

	public function __construct(array $opts=[]) {
		parent::__construct($opts);
		$this->__constructAuth();
	}

}