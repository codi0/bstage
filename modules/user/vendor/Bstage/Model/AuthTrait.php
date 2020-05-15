<?php

namespace Bstage\Model;

trait AuthTrait {

	protected $password = '';
	protected $activationKey = '';

	public function __construct(array $opts=[]) {
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

	public function login($email, $password) {
		//query user record
		$data = $this->app->db->select('user', [
			'limit' => 1,
			'where' => [
				'logic' => 'or',
				'fields' => [
					'username' => $email,
					'email' => $email,
				],
			],
		]);
		//valid user and password?
		if(!$data || !$this->app->crypt->verifyPwd($password, $data['password'])) {
			return false;
		}
		//hydrate model
		$this->__construct((array) $data);
		//update session
		$this->app->session->set('user_id', $this->id);
		$this->app->session->regenerate();
		//success
		return true;
	}

	public function logout() {
		return $this->app->session->destroy();
	}

	public function register(array $data) {
		//hydrate model
		$this->__construct($data);
		//save model
		$this->app->orm->save($this);
		//valid model?
		if(!$this->id) {
			return false;
		}
		//update session
		$this->app->session->set('user_id', $this->id);
		$this->app->session->regenerate();
		//EVENT: auth.registered
		$this->app->events->dispatch('auth.registered', [
			'user' => $this,
		]);
		//set mail content
		$subject = "Activate your %from_name% account";
		$body  = "Hi %to_name%,\n\n";
		$body .= "Welcome to %from_name%! To complete your registration, please click the link below to verify your email address:\n\n";
		$body .= "%activate_link%\n\n";
		$body .= "All the best,\n";
		$body .= "%from_name%";
		//send mail
		$this->app->mail->send($this->email, $subject, $body, [
			'template' => 'user.register',
			'to_name' => $this->username ?: 'there',
			'activate_link' => $this->app->url('activate', [
				'id' => $this->id,
				'key' => $this->activationKey,
			]),
		]);
		//success
		return true;
	}

	public function activate($userId, $activationKey, $password=null) {
		//get user model
		$user = $this->app->orm->get('auth', $userId);
		//valid user?
		if(!$user->id) {
			return false;
		}
		//account already activated?
		if($user->isActive() && $password === null) {
			return true;
		}
		//activation key matches?
		if($user->activationKey !== $activationKey) {
			return false;
		}
		//update password?
		if($password !== null) {
			$user->changePassword($password);
		}
		//update state
		$user->status = 1;
		$user->generateActivationKey();
		//save now
		return $this->app->orm->save($user);
	}

	public function forgotPwd($email) {
		//get user model
		$user = $this->app->orm->get('auth', [
			'where' => [
				'logic' => 'or',
				'fields' => [
					'username' => $email,
					'email' => $email,
				],
			]
		]);
		//user exists?
		if(!$user->id) {
			return false;
		}
		//set mail content
		$subject = "Reset your %from_name% password";
		$body  = "Hi %to_name%,\n\n";
		$body .= "Please click the link below to reset your password:\n\n";
		$body .= "%reset_link%\n\n";
		$body .= "If you did not request a password reset, please ignore this email.\n\n";
		$body .= "All the best,\n";
		$body .= "%from_name%";
		//send mail
		$this->app->mail->send($user->email, $subject, $body, [
			'template' => 'user.forgot',
			'to_name' => $user->username ?: 'there',
			'reset_link' => $this->app->url('reset', [ 'id' => $user->id, 'key' => $user->activationKey ]),
		]);
		//update user
		return $this->app->orm->save($user);
	}

	public function resetPwd($userId, $activationKey, $password) {
		return $password && $this->activate($userId, $activationKey, $password);
	}

	public function changeEmail($email) {
		$this->email = $email;
	}

	public function changeUsername($username) {
		$this->username = $username;
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