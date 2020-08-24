<?php

namespace Bauth\View\Shortcode;

class Login {

	public function parse(array $params, $app) {
		//create login form
		$form = $app->form('login', [
			'method' => 'POST',
			'model' => $app->auth->model(),
			'autosave' => false,
			'onSuccess' => function($values, &$errors, &$message) use($app) {
				//user login?
				if(!$app->auth->login($values['email'], $values['password'])) {
					$errors[] = 'Sign in failed, please try again';
					return false;
				}
				//get url
				$url = $app->input('redirect', 'GET') ?: 'account';
				//redirect user
				$app->redirect($url, [
					'query' => $_GET,
				]);
			},
		]);
		//add form fields
		$form->input('email', [ 'validate' => 'required', 'override' => true ]);
		$form->password('password', [ 'validate' => 'length(8,30)', 'override' => true ]);
		$form->submit('Sign in');
		//return
		return $form;
	}

}