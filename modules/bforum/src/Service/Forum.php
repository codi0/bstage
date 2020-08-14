<?php

namespace Bforum\Service;

class Forum extends \Bstage\App\Service {

	public function searchTopics(array $opts=[]) {
		//set opts
		$opts = array_merge([
			'limit' => $this->app->config('forum.paging.search') ?: 20,
			'offset' => 0,
			'page' => 0,
			'score' => 0,
			'keywords' => null,
			'category_id' => null,
		], $opts);
		//calc offset?
		if($opts['page'] && $opts['limit']) {
			$opts['offset'] = $opts['limit'] * ($opts['page'] - 1);
		}
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
			'limit' => $opts['limit'],
			'offset' => $opts['offset'],
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
			'topics' => $topics,
			'count' => $count['count'],
			'category' => ($topics && $opts['category_id']) ? $topics[0]['category_name'] : null,
			'page' => $opts['page'],
			'per_page' => $opts['limit'],
		];
	}

	public function getTopic($topicId, $page=null) {
		//set vars
		$with = [];
		$page = (int) $page;
		$perPage = $this->app->config->get('forum.paging.topic') ?: 20;
		//with messages?
		if($page > 0) {
			$with = [
				'messages' => [
					'query' => [
						'limit' => $perPage,
						'offset' => ($perPage * ($page-1)),
					],
				],
			];
		}
		//query model
		$topic = $this->app->model('forumTopic', [
			'query' => [
				'where' => [
					'id' => $topicId,
				],
			],
			'with' => $with,
		]);
		//set page vars
		$topic->page = $page;
		$topic->perPage = $perPage;
		//return
		return $topic;
	}

}