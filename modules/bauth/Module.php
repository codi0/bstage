<?php

namespace Bauth;

class Module extends \Bstage\App\Module {

	protected $version = '1.0.0';

	protected function init() {
		//is web app?
		if($this->app->meta('type') === 'web') {
			//register routes
			$this->app->route('/login', 'Bauth\Controller\Login');
			$this->app->route('/logout', 'Bauth\Controller\Logout');
			$this->app->route('/register', 'Bauth\Controller\Register');
			$this->app->route('/activate', 'Bauth\Controller\Activate');
			$this->app->route('/forgot', 'Bauth\Controller\Forgot');
			$this->app->route('/reset', 'Bauth\Controller\Reset');
			//register middleware
			$this->app->middleware('auth.web', 'Bauth\Middleware\AuthWeb');
		}
		//register services
		$this->app->service('auth', function(array $opts, $app) {
			return new \Bauth\Service\Auth(array_merge([
				'app' => $app,
			], $opts));
		});
	}

}