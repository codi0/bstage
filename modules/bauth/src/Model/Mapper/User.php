<?php

namespace Bauth\Model\Mapper;

class User extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'username' => [
			'validate' => 'alphanumeric|length(3,16)',
			'save' => true,
		],
		'email' => [
			'validate' => 'xss|email',
			'save' => true,
		],
		'avatar' => [
			'validate' => 'xss|url',
			'save' => false,
		],
		'status' => [
			'validate' => 'int',
			'save' => true,
		],
	];

}