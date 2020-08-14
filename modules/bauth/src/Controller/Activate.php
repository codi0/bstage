<?php

namespace Bauth\Controller;

class Activate extends \Bstage\App\Controller {

	public function index($req, $res) {
		//get input
		$userId = $this->app->input('id', 'GET');
		$activationKey = $this->app->input('key', 'GET');
		//attempt activation
		$activated = $this->app->auth->activate($userId, $activationKey);
		//render view
		$this->app->view('activate', [
			'activated' => $activated,
		]);
	}

}