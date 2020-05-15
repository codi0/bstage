<?php

namespace Bstage\Model\Mapper;

class Auth extends User {

	protected $fields = [
		'password' => [
			'validate' => 'length(60,255)',
			'save' => true,
		],
		'activationKey' => [
			'validate' => 'length(64)',
			'save' => true,
		],
	];

}