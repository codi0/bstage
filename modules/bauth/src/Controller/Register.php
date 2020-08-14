<?php

namespace Bauth\Controller;

class Register extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->view('register');
	}

}