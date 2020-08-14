<?php

namespace App;

class Module extends \Bstage\App\Module {

	protected $version = '1.0.0';

	protected function init() {
		//register routes
		$this->app->route('*', 'App\Controller\Home@notFound');
		$this->app->route('/', 'App\Controller\Home@index');
	}

}