<?php

namespace Bstage\Model\Mapper;

class ForumCategory extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'name' => [
			'validate' => 'xss|length(3,32)',
			'save' => true,
		],
		'position' => [
			'validate' => 'int',
			'save' => true,
		],
		'status' => [
			'validate' => 'int',
			'save' => true,
		],
	];

	public static function findDefault() {
		return [
			'fields' => [
				'c.*',
				'COUNT(t.id) as topic_count',
			],
			'table' => '{forum_category} c',
			'join' => [
				'type' => 'left',
				'table' => '{forum_topic} t',
				'on' => 'c.id = t.category_id AND t.status = 1',
			],
			'where' => [
				'c.status' => 1,
			],
			'group' => 'c.id',
			'order' => 'c.position ASC',
		];	
	}

}