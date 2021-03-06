<?php

namespace Bauth\Model;

class User extends \Bstage\Orm\Model {

	protected $id = 0;
	protected $username = '';
	protected $email = '';
	protected $avatar = '';
	protected $status = 0;

	protected $permissions = [];

	public function __construct(array $opts=[]) {
		//call root
		$this->__constructRoot($opts);
		//generate avatar?
		if($this->email && !$this->avatar) {
			$hash = md5(strtolower(trim($this->email)));
			$this->avatar = 'https://www.gravatar.com/avatar/' . $hash;
		}
	}

	public function getAvatar($size=80) {
		return $this->avatar . '?s=' . $size;
	}

	public function isActive() {
		return $this->status == 1;
	}

	public function can($permission) {
		return isset($this->permissions[$permission]) && $this->permissions[$permission];
	}

}