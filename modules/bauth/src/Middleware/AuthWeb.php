<?php

namespace Bauth\Middleware;

class AuthWeb extends \Bstage\Http\Middleware\AbstractMiddleware {

	public function process($request, $next) {
		//get params
		$uri = $request->getUri();
		$app = $request->getAttribute('app');
		$route = $request->getAttribute('route');
		//redirect to login?
		if(!$app->auth->id() && $route->getPrefix()) {
			$app->redirect('login', [
				'redirect' => trim($uri->getPathInfo(), '/') . ($_GET ? '?' . http_build_query($_GET) : ''),
			]);
		}
		//redirect to account?
		if($app->auth->id() && in_array($route->getName(), [ 'login', 'register', 'forgot', 'reset' ])) {
			$app->redirect('account', $_GET);
		}
		//return (before)
		return $next($request);
	}

}