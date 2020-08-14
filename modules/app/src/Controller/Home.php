<?php

namespace App\Controller;

class Home extends \Bstage\App\Controller {

	public function index($req, $res) {
		$this->app->view('home');
	}

	public function notFound($req, $res) {
		$res->withStatus(404);
		$this->app->view('404');
	}

}