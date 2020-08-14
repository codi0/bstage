<?php

namespace Bauth\Controller;

class Reset extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->view('reset');
	}

}