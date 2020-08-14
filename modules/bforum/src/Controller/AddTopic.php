<?php

namespace Bforum\Controller;

class AddTopic extends \Bstage\App\Controller {

	public function index($req, $res) {
		//render view
		$this->app->view('forum-add', [
			'meta' => [
				'title' => 'Start a discussion',
				'noindex' => true,
			],	
		]);
	}

}