<?php

namespace Bforum\Controller;

class ViewTopic extends \Bstage\App\Controller {

	public function index($req, $res) {
		//get input
		$page = (int) $this->app->input('p', 'GET') ?: 1;
		$topicId = (int) $req->getAttribute('route')->getParam('id');
		//get model
		$topic = $this->app->forum->getTopic($topicId, $page);
		//valid model?
		if(!$topic->id) {
			return $this->app->route('404');
		}
		//render view
		$this->app->view('forum-view', [
			'topic' => $topic,
			'meta' => [
				'title' => $topic->title,
				'canonical' => $topic->page > 1 ? $this->app->url(null, [ 'p' => $topic->page ]) : '',
				'page' => $topic->page,
				'page_total' => ceil($topic->messageCount / $topic->perPage),
			],
		]);
	}

}