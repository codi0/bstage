<?php


/* MIDDLWARE */

//Middlware: route authentication
$app->httpMiddleware->add('auth.web', function($request, $next) use($app) {
	//get params
	$uri = $request->getUri();
	$route = $request->getAttribute('route');
	//redirect to login?
	if(!$app->auth->id && $route->getPrefix()) {
		$app->redirect('login', [
			'redirect' => trim($uri->getPathInfo(), '/') . ($_GET ? '?' . http_build_query($_GET) : ''),
		]);
	}
	//redirect to account?
	if($app->auth->id && in_array($route->getName(), [ 'login', 'register', 'forgot', 'reset' ])) {
		$app->redirect('account', $_GET);
	}
	//return (before)
	return $next($request);
});


/* ROUTES */

//Route: login
$app->route('/login', function($req, $res) use($app) {
	$app->view('login');
});

//Route: logout
$app->route('/logout', function($req, $res) use($app) {
	$app->auth->logout();
	$app->redirect('login');
});

//Route: register 
$app->route('/register', function($req, $res) use($app) {
	$app->view('register');
});

//Route: activate account
$app->route('/activate', function($req, $res) use($app) {
	//get input
	$userId = $app->input('id', 'GET');
	$activationKey = $app->input('key', 'GET');
	//attempt activation
	$activated = $app->auth->activate($userId, $activationKey);
	//render view
	$app->view('activate', [
		'activated' => $activated,
	]);
});

//Route: forgot password
$app->route('/forgot', function($req, $res) use($app) {
	$app->view('forgot');
});

//Route: reset password
$app->router->add('/reset', function($req, $res) use($app) {
	$app->view('reset');
});