<?php

namespace Bstage\Service;

class Forum {

	protected $app;

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function findTopics($limit, $offset, array $opts=[]) {
		//set opts
		$opts = array_merge([
			'score' => 0,
			'keywords' => null,
			'category_id' => null,
		], $opts);
		//count query
		$countSql = [
			'fields' => [
				'COUNT(t1.id) as count',
			],
			'where' => [
				't1.category_id' => $opts['category_id'],
				't1.status' => 1,
			],
			'search' => [
				'fields' => 't1.title, m1.text',
				'term' => $opts['keywords'],
				'score' => $opts['score'],
				'count' => true,
			],
			'limit' => 1,
		];
		//topics query
		$topicSql = [
			'fields' => [
				't1.id',
				't1.title',
				'c1.id as category_id',
				'c1.name as category_name',
				'u1.id AS author_id',
				'u1.username AS author_name',
				'u2.id AS last_msg_author_id',
				'u2.username AS last_msg_author_name',
				'MAX(m1.date_created) AS last_msg_time',
				'(COUNT(m2.id) - 1) as reply_num',
			],
			'join' => [
				[
					'type' => 'inner',
					'table' => '{forum_category} c1',
					'on' => 'c1.id = t1.category_id',
				],
				[
					'type' => 'inner',
					'table' => '{user} u1',
					'on' => 'u1.id = t1.user_id',
				],
				[
					'type' => 'inner',
					'table' => '{forum_message} m1',
					'on' => 'm1.id = (SELECT id FROM {forum_message} WHERE topic_id = t1.id AND status = 1 ORDER BY id DESC LIMIT 1)',
				],
				[
					'type' => 'inner',
					'table' => '{forum_message} m2',
					'on' => 'm2.topic_id = t1.id',
				],
				[
					'type' => 'inner',
					'table' => '{user} u2',
					'on' => 'u2.id = m1.user_id',
				],
			],
			'where' => [
				't1.category_id' => $opts['category_id'],
				't1.status' => 1,
			],
			'search' => [
				'fields' => 't1.title, m1.text',
				'term' => $opts['keywords'],
				'score' => $opts['score'],
			],
			'group' => 't1.id',
			'order' => 'last_msg_time DESC',
			'limit' => $limit,
			'offset' => $offset,
		];
		//run search?
		if($opts['keywords']) {
			//add count join
			$countSql['join'] = [
				'type' => 'inner',
				'table' => '{forum_message} m1',
				'on' => 't1.id = m1.topic_id AND m1.status = 1',
			];
		}
		//count
		$count = $this->app->db->select('{forum_topic} t1', $countSql);
		//count
		$topics = $this->app->db->select('{forum_topic} t1', $topicSql);
		//return
		return [
			'count' => $count['count'],
			'topics' => $topics,
			'category' => ($topics && $opts['category_id']) ? $topics[0]['category_name'] : null,
		];
	}

}