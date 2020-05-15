<?php

namespace Bstage\Service;

class Auth {

	protected $app;
	protected $user;

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//get user model?
		if(!$this->user) {
			//get user ID
			$uid = $this->app->session->get('user_id');
			//create user model
			$this->user = $this->app->orm->get('userAuth', [
				'alias' => 'user',
				'query' => $uid,
			]);
		}
	}

	public function __call($method, $args) {
		//call user method?
		if(method_exists($this->user, $method)) {
			return $this->user->$method(...$args);
		}
		//not found
		throw new \Exception("Method not found: $method");
	}

	public function model() {
		return $this->user;
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
		//get user model
		$this->user = $this->app->orm->get('userAuth', [
			'data' => $data,
		]);
		//update session
		$this->app->session->set('user_id', $this->user->id());
		$this->app->session->regenerate();
		//return ID
		return $this->user->id();
	}

	public function logout() {
		$this->user = null;
		return $this->app->session->destroy();
	}

	public function register(array $data) {
		//get user model
		$this->user = $this->app->orm->get('userAuth', [
			'data' => $data,
		]);
		//set password
		$this->user->changePassword($data['password']);
		$this->user->refreshActivationKey();
		//save user model?
		if(!$this->app->orm->save($this->user)) {
			return false;
		}
		//EVENT: auth.registered
		$this->app->events->dispatch('auth.registered', [
			'user' => $this->user,
			'authService' => $this,
		]);
		//set mail content
		$subject = "Activate your %from_name% account";
		$body  = "Hi %to_name%,\n\n";
		$body .= "Welcome to %from_name%! To complete your registration, please click the link below to verify your email address:\n\n";
		$body .= "%activate_link%\n\n";
		$body .= "All the best,\n";
		$body .= "%from_name%";
		//send mail
		$this->app->mail->send($this->user->getEmail(), $subject, $body, [
			'template' => 'user.register',
			'to_name' => $this->user->getUsername() ?: 'there',
			'activate_link' => $this->app->url('activate', [
				'id' => $this->user->id(),
				'key' => $this->user->getActivationKey(),
			]),
		]);
		//update session
		$this->app->session->set('user_id', $this->user->id());
		$this->app->session->regenerate();
		//success
		return $this->user->id();
	}

	public function activate($userId, $activationKey, $password=null) {
		//get user model
		$user = $this->app->orm->get('userAuth', $userId);
		//valid user?
		if(!$user->id()) {
			return false;
		}
		//account already activated?
		if($user->isActive() && $password === null) {
			return true;
		}
		//activation key matches?
		if($user->getActivationKey() !== $activationKey) {
			return false;
		}
		//update password?
		if($password !== null) {
			$user->changePassword($password);
		}
		//activate
		$user->activate();
		//update user
		return $this->app->orm->save($user);
	}

	public function forgotPwd($email) {
		//get user model
		$user = $this->app->orm->get('userAuth', [
			'where' => [
				'logic' => 'or',
				'fields' => [
					'username' => $email,
					'email' => $email,
				],
			]
		]);
		//user exists?
		if(!$user->id()) {
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
		$this->app->mail->send($user->getEmail(), $subject, $body, [
			'template' => 'user.forgot',
			'to_name' => $user->getUsername() ?: 'there',
			'reset_link' => $this->app->url('reset', [ 'id' => $user->id(), 'key' => $user->getActivationKey() ]),
		]);
		//update user
		return $this->app->orm->save($user);
	}

	public function resetPwd($userId, $activationKey, $password) {
		return $password && $this->activate($userId, $activationKey, $password);
	}

}