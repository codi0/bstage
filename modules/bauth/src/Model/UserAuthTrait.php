<?php

namespace Bauth\Model;

trait UserAuthTrait {

	protected $password = '';
	protected $activationKey = '';

	public function __construct(array $opts=[]) {
		return $this->__constructAuth($opts);
	}

	public function __constructAuth(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//hash password?
		if($this->password) {
			$this->changePassword($this->password);
		}
		//validate key?
		if($this->activationKey) {
			$split = explode('::', $this->activationKey);
			if(!isset($split[1]) || intval($split[1]) < (time() - 3600)) {
				$this->activationKey = '';
			}
		}
		//generate key?
		if(!$this->activationKey) {
			$this->generateActivationKey();
		}
	}

	public function activate() {
		$this->status = 1;
		$this->generateActivationKey();
	}

	public function changePassword($password) {
		$this->password = $this->app->crypt->hashPwd($password);
	}

	protected function generateActivationKey($length=64) {
		$suffix = '::' . time();
		$length = $length - strlen($suffix);
		$this->activationKey = $this->app->crypt->nonce($length) . $suffix;
	}

}