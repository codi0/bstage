<?php

namespace Bauth\Controller;

class Forgot extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->view('forgot');
	}

}