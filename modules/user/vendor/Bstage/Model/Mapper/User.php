<?php

namespace Bstage\Model\Mapper;

class User extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'username' => [
			'validate' => 'xss|length(3,16)',
			'save' => true,
		],
		'email' => [
			'validate' => 'xss|email',
			'save' => true,
		],
		'status' => [
			'validate' => 'int',
			'save' => true,
		],
	];

}