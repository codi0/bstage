<?php

namespace Bstage\Model\Mapper;

class ForumTopic extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'title' => [
			'validate' => 'xss|length(3,128)',
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
		'category' => [
			'relation' => 'belongsTo',
			'model' => 'forumCategory',
			'column' => 'category_id',
			'lazy' => true,
		],
		'messages' => [
			'relation' => 'hasMany',
			'model' => 'forumMessage',
			'cascade' => [ 'insert' ],
			'eager' => [ 'user' ],
			'lazy' => true,
		],
	];

	public static function findDefault() {
		return [
			'fields' => [
				't.*',
				'COUNT(m.id) as message_count',
			],
			'table' => '{forum_topic} t',
			'join' => [
				'type' => 'left',
				'table' => '{forum_message} m',
				'on' => 't.id = m.topic_id AND m.status = 1',
			],
			'where' => [
				't.status' => 1,
			],
			'group' => 't.id',
		];
	}

	public static function findLatest() {
		return [
			'fields' => [
				't1.id',
				't1.title',
				'u1.id AS author_id',
				'u1.username AS author_name',
				'u2.id AS last_msg_author_id',
				'u2.username AS last_msg_author_name',
				'MAX(m1.date_created) AS last_msg_time',
			],
			'table' => '{forum_topic} t1',
			'join' => [
				[
					'type' => 'inner',
					'table' => '{user} u1',
					'on' => 't1.user_id = u1.id',
				],
				[
					'type' => 'inner',
					'table' => '{forum_message} m1',
					'on' => 'm1.id = (SELECT m2.id FROM {forum_message} m2 WHERE m2.topic_id = t1.id AND m2.status = 1 ORDER BY m2.id DESC LIMIT 1)',
				],
				[
					'type' => 'inner',
					'table' => '{user} u2',
					'on' => 'm1.user_id = u2.id',
				],
			],
			'where' => [
				't1.status' => 1,
			],
			'group' => 't1.id',
			'order' => 'last_msg_time DESC',
		];	
	}

}