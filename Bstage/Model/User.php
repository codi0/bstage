<?php

namespace Bstage\Model;

class User extends \Bstage\Model\AbstractModel {

	protected $_data = [
		'id' => [
			'value' => 0,
			'validate' => 'int',
			'readonly' => true,
		],
		'email' => [
			'value' => '',
			'validate' => 'email|length(3,128)',
		],
		'password' => [
			'value' => '',
			'validate' => 'length(8,128)',
			'readonly' => true,
		],
		'status' => [
			'value' => 0,
			'validate' => 'int',
		],
		'activationKey' => [
			'value' => '',
			'validate' => 'length(64)',
			'readonly' => true,
		],
		'dateCreated' => [
			'value' => '',
			'readonly' => true,
		],
	];

	public function __construct(array $opts=array()) {
		//call parent
		parent::__construct($opts);
		//hydrate model?
		if(!$this->id && $userId = $this->isAuth()) {
			$this->hydrate([ 'query' => [ 'id' => $userId ], 'changed' => false ]);
		}
	}

	public function isAuth() {
		return $this->_app->session->get('user_id');
	}

	public function register(array $data, $redirectUrl=null) {
		//cache login details
		$email = $data['email'];
		$password = $data['password'];
		//hash password
		$data['password'] = $this->_app->crypt->hashPwd($password);
		//set activation key
		$data['activation_key'] = $this->_app->crypt->nonce(64);
		//hydrate model
		$this->hydrate([ 'data' => $data, 'changed' => true ]);
		//save model?
		if(!$this->save()) {
			return false;
		}
		//trigger event?
		if($this->_app->events) {
			//EVENT: user.registered
			$this->_app->events->dispatch('user.registered', [
				'user' => $this,
			]);
		}
		//login user
		return $this->login($email, $password, $redirectUrl);
	}

	public function login($email, $password, $redirectUrl=null) {
		//user exists?
		if(!$data = $this->_app->db->select($this->_table, [ 'email' => $email ], 1)) {
			return false;
		}
		//verify password?
		if(!$this->_app->crypt->verifyPwd($password, $data['password'])) {
			return false;
		}
		//hydrate model
		$this->hydrate([ 'data' => $data, 'changed' => false ]);
		//update session
		$this->_app->session->set('user_id', $this->id);
		//regenerate session
		$this->_app->session->regenerate();
		//redirect user?
		if($redirectUrl) {
			return $this->_app->redirect($redirectUrl);
		}
		//success
		return $this->id;
	}

	public function logout() {
		//destroy session
		$this->_app->session->destroy();
		//success
		return true;
	}

}