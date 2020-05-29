<?php

namespace Bstage\View\Shortcode;

class Register {

	public function parse(array $params, $app) {
		//create registration form
		$form = $app->form('register', [
			'method' => 'POST',
			'model' => $app->auth,
			'onSave' => function($values, &$errors) use($app) {
				//attempt user registration
				if(!$app->auth->register($values)) {
					$errors[] = 'Registration failed, please try again';
				}
				//clear values
				return [];
			},
			'onSuccess' => function($values, &$errors, &$message) use($app) {
				//get url
				$url = $app->input('redirect', 'GET') ?: 'account';
				//redirect user
				$app->redirect($url);
			},
		]);
		//add core fields
		$form->input('username', [ 'validate' => 'unique(user.username)' ]);
		$form->input('email', [ 'validate' => 'unique(user.email)' ]);
		$form->password('password', [ 'validate' => 'length(8,30)', 'override' => true, 'toggle' => true, ]);
		$form->captcha('Verify', [ 'label' => 'Are you human?' ]);
		$form->submit('Create account');
		//return
		return $form;
	}

}