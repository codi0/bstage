<?php

namespace Bforum\Controller;

class ListTopics extends \Bstage\App\Controller {

	public function index($req, $res) {
		//input params
		$keywords = $this->app->input('q', 'GET');
		$page = (int) $this->app->input('p', 'GET') ?: 1;
		$category = (int) $req->getAttribute('route')->getParam('id');
		//search topics
		$topics = $this->app->forum->searchTopics([
			'page' => $page,
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
		$this->app->view('forum-search', [
			'topics' => $topics['topics'],
			'search' => [
				'keywords' => $keywords,
			],
			'meta' => [
				'title' => ($keywords || $category) ? $title : '',
				'page' => $page,
				'page_total' => ceil($topics['count'] / $topics['per_page']),
				'noindex' => $keywords || $page > 1,
			],
		]);
	}

}