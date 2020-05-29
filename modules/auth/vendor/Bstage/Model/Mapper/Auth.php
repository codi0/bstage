<?php

namespace Bstage\Model\Mapper;

class Auth extends User {

	protected $name = 'user';

	protected $fields = [
		'password' => [
			'validate' => 'length(60,255)',
			'save' => true,
			'onHydrate' => 'crypt.hashPwd',
		],
		'activationKey' => [
			'validate' => 'length(64)',
			'save' => true,
		],
	];

}