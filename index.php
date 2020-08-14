<?php

//load bootstrap file?
if(!function_exists('bstage')) {
	include('vendor/Bstage/App/Bootstrap.php');
}

//create app
$app = bstage('app', [
	'debug' => true,
]);

//run app
$app->run();