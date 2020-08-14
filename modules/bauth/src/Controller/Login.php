<?php

namespace Bauth\Controller;

class Login extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->view('login');
	}

}