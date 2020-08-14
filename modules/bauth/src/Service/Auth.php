<?php

namespace Bauth\Service;

class Auth extends \Bstage\App\Service {

	protected $user;

	public function id() {
		return $this->model()->id;
	}

	public function isActive() {
		return $this->model()->isActive();
	}

	public function model() {
		//check session?
		if(!$this->user) {
			//user ID found?
			if($userId = $this->app->session->get('user_id')) {
				$query = [ 'id' => $userId ];
			} else {
				$query = [];
			}
			//query user model
			$this->user = $this->app->orm->get('userAuth', [
				'alias' => 'user',
				'query' => $query,
			]);
		}
		//return
		return $this->user;
	}

	public function login($email, $password) {
		//query user record
		$user = $this->app->orm->get('userAuth', [
			'alias' => 'user',
			'query' => [
				'limit' => 1,
				'where' => [
					'logic' => 'or',
					'fields' => [
						'username' => $email,
						'email' => $email,
					],
				],
			],
		]);
		//valid user and password?
		if(!$user->id || !$this->app->crypt->verifyPwd($password, $user->password)) {
			return false;
		}
		//update session
		$this->app->session->set('user_id', $user->id);
		$this->app->session->regenerate();
		//cache model
		$this->user = $user;
		//success
		return true;
	}

	public function logout() {
		//reset model
		$this->user = $this->app->orm->get('userAuth', [
			'alias' => 'user',
		]);
		//destroy session
		return $this->app->session->destroy();
	}

	public function register(array $data) {
		//create model
		$user = $this->app->orm->get('userAuth', [
			'alias' => 'user',
			'data' => $data,
		]);
		//save model
		$this->app->orm->save($user);
		//valid model?
		if(!$user->id) {
			return false;
		}
		//update session
		$this->app->session->set('user_id', $user->id);
		$this->app->session->regenerate();
		//cache model
		$this->user = $user;
		//EVENT: auth.registered
		$this->app->events->dispatch('auth.registered', [
			'auth' => $this,
		]);
		//send email
		$this->sendActivationEmail();
		//success
		return true;
	}

	public function sendActivationEmail() {
		//already active?
		if($this->isActive()) {
			return true;
		}
		//get user model
		$user = $this->model();
		//ensure activation key saved
		$this->app->orm->save($user);
		//set mail content
		$subject = "Activate your %from_name% account";
		$body  = "Hi %to_name%,\n\n";
		$body .= "Welcome to %from_name%! To complete your registration, please click the link below to verify your email address:\n\n";
		$body .= "%activate_link%\n\n";
		$body .= "All the best,\n";
		$body .= "%from_name%";
		//send mail
		return $this->app->mail->send($user->email, $subject, $body, [
			'template' => 'user.register',
			'to_name' => $user->username ?: 'there',
			'activate_link' => $this->app->url('activate', [
				'id' => $user->id,
				'key' => $user->activationKey,
			]),
		]);
	}

	public function activate($userId, $activationKey, $password=null) {
		//get user model
		$user = $this->app->orm->get('userAuth', [
			'alias' => 'user',
			'query' => [
				'id' => $userId,
			],
		]);
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
		//activate user
		$user->activate();
		//can save?
		if(!$this->app->orm->save($user)) {
			return false;
		}
		//cache model
		$this->user = $user;
		//success
		return true;
	}

	public function forgotPwd($email) {
		//get user model
		$user = $this->app->orm->get('userAuth', [
			'alias' => 'user',
			'query' => [
				'where' => [
					'logic' => 'or',
					'fields' => [
						'username' => $email,
						'email' => $email,
					],
				],
			],
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
		$this->model()->email = $email;
	}

	public function changeUsername($username) {
		$this->model()->username = $username;
	}

	public function changePassword($password) {
		$this->model()->changePassword($password);
	}

}