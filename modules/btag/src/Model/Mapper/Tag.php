<?php

namespace Btag\Model\Mapper;

class Tag extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'name' => [
			'validate' => 'xss|length(3,32)',
			'save' => true,
		],
		'taxonomy' => [
			'validate' => 'xss|length(3,16)',
			'save' => true,
		],
		'parentId' => [
			'validate' => 'int',
			'save' => true,
		],
		'order' => [
			'validate' => 'int',
			'save' => true,
		],
		'status' => [
			'validate' => 'int',
			'save' => true,
		],
	];

}