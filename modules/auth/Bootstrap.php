<?php

//Service: authentication
$app->auth = function(array $opts, $app) {
	//set vars
	$query = [];
	//is logged in?
	if($userId = $app->session->get('user_id')) {
		$query = [ 'id' => $userId ];
	}
	//create auth model
	return $app->orm->get('auth', [
		'alias' => 'user',
		'query' => $query,
	]);
};

//web call?
if($app->meta('scope') === 'web') {
	include('Web.php');
}