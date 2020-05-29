<?php

namespace Bstage\Model\Mapper;

class ForumMessage extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'text' => [
			'validate' => 'length(3,3000)',
			'save' => true,
		],
		'dateCreated' => [
			'save' => false,
		],
		'user' => [
			'relation' => 'belongsTo',
			'model' => 'user',
			'lazy' => true,
		],
		'topic' => [
			'relation' => 'belongsTo',
			'model' => 'forumTopic',
			'lazy' => true,
		],
	];

	public static function findDefault() {
		return [
			'where' => [
				'status' => 1,
			],
		];	
	}

}