<?php

namespace Bstage\Model\Mapper;

class UserAuth extends User {

	protected $fields = [
		'password' => [
			'save' => true,
			'validate' => 'length(60,255)',
			'onHydrate' => 'crypt.hashPwd',
		],
		'activationKey' => [
			'save' => true,
			'validate' => 'length(64)',
		],
	];

}