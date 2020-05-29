<?php

namespace Bstage\View\Shortcode;

class ForgotPwd {

	public function parse(array $params, $app) {
		//forgotten password form
		$form = $app->form('forgot', [
			'method' => 'POST',
			'model' => $app->auth,
			'onSuccess' => function($values, &$errors, &$message) use($app) {
				//send email
				$app->auth->forgotPwd($values['email']);
				//set message
				$message = 'If this account exists, you will shortly receive a password reset email.';
			},
		]);
		//add form fields
		$form->input('email', [ 'validate' => 'required', 'override' => true ]);
		$form->captcha('Verify', [ 'label' => 'Are you human?' ]);
		$form->submit('Reset password');
		//return
		return $form;
	}

}