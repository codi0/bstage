<?php

//load bootstrap file?
if(!function_exists('bstage')) {
	include('vendor/Bstage/App/Bootstrap.php');
}

//create app
$app = bstage('app', [
	'debug' => true,
]);

//define routes
$app->route('/', function($request, $response) {
	echo 'Hello World';
});

//run app
$app->run();