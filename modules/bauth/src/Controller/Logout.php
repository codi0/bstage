<?php

namespace Bauth\Controller;

class Logout extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->auth->logout();
		$this->app->redirect('login');
	}

}