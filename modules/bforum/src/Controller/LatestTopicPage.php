<?php

namespace Bforum\Controller;

class LatestTopicPage extends \Bstage\App\Controller {

	public function index($req, $res) {
		//get input
		$topicId = (int) $req->getAttribute('route')->getParam('id');
		//get model
		$topic = $this->app->forum->getTopic($topicId);
		//valid model?
		if(!$topic->id) {
			return $this->app->route('404');
		}
		//get page
		$page = ceil($topic->messageCount / $topic->perPage);
		$page = ($page > 1) ? '?p=' . $page : '' . (isset($_GET['replied']) ? '#last' : '');
		//redirect
		$this->app->redirect('forum/topic/' . $topicId . $page);
	}

}