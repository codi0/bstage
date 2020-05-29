<?php


/* REQUIRES */

//Auth module
$app->module('auth');


/* SERVICES */

//Service: forum
$app->forum = function(array $opts, $app) {
	return new \Bstage\Service\Forum(array_merge([
		'app' => $app,
	], $opts));
};


/* ROUTES */

//Route: forum home
$app->route('/forum/<:id?>', function($req, $res) use($app) {
	//set vars
	$perPage = (int) $app->config('forum.paging.results') ?: 20;
	//input params
	$keywords = $app->input('q', 'GET');
	$page = (int) $app->input('p', 'GET') ?: 1;
	$category = (int) $req->getAttribute('route')->getParam('id');
	//get topics
	$topics = $app->forum->findTopics($perPage, $perPage * ($page-1), [
		'category_id' => $category ?: null,
		'keywords' => $keywords,
	]);
	//get page title
	if($keywords) {
		$title = 'Forum search: ' . $keywords;
	} elseif($category) {
		$title = $topics['category'] . ' forum';
	} else {
		$title = '';
	}
	//render view
	$app->view('forum-search', [
		'topics' => $topics['topics'],
		'search' => [
			'keywords' => $keywords,
		],
		'meta' => [
			'title' => ($keywords || $category) ? $title : '',
			'page' => $page,
			'page_total' => ceil($topics['count'] / $perPage),
			'noindex' => $keywords || $page > 1,
		],
	]);
});

//Route: forum topic
$app->route('/forum/topic/<:id>', function($req, $res) use($app) {
	//set vars
	$page = (int) $app->input('p', 'GET') ?: 1;
	$perPage = (int) $app->config('forum.paging.topics') ?: 20;
	$topicId = (int) $req->getAttribute('route')->getParam('id');
	//get topic
	$topic = $app->model('forumTopic', [
		'query' => [
			'where' => [
				'id' => $topicId,
			],
		],
		'with' => [
			'messages' => [
				'query' => [
					'limit' => $perPage,
					'offset' => ($perPage * ($page-1)),
				],
			],
		],
	]);
	//valid request?
	if(!$topic->id) {
		return $app->route('404');
	}
	//render view
	$app->view('forum-view', [
		'topic' => $topic,
		'meta' => [
			'title' => $topic->title,
			'canonical' => $page > 1 ? $app->url(null, [ 'p' => $page ]) : '',
			'page' => $page,
			'page_total' => ceil($topic->messageCount / $perPage),
		],
	]);
});

//Route: go to latest topic page
$app->route('/forum/topic/<:id>/latest', function($req, $res) use($app) {
	//set vars
	$perPage = (int) $app->config('forum.paging.topics') ?: 20;
	$topicId = (int) $req->getAttribute('route')->getParam('id');
	//get topic
	$topic = $app->model('forumTopic', [
		'query' => [
			'where' => [
				'id' => $topicId,
			],
		],
	]);
	//valid request?
	if(!$topic->id) {
		return $app->route('404');
	}
	//get page
	$page = ceil($topic->messageCount / $perPage);
	$page = ($page > 1) ? '?p=' . $page : '' . (isset($_GET['replied']) ? '#last' : '');
	//redirect
	$app->redirect('forum/topic/' . $topicId . $page);
});

//Route: add new topic
$app->route('/forum/add/<:id?>', function($req, $res) use($app) {
	//render view
	$app->view('forum-add', [
		'meta' => [
			'noindex' => true,
		],	
	]);
});