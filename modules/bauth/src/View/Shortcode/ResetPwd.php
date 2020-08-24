<?php

namespace Bauth\View\Shortcode;

class ResetPwd {

	public function parse(array $params, $app) {
		//get input
		$userId = $app->input('id', 'GET');
		$activationKey = $app->input('key', 'GET');
		//valid request?
		if(!$userId || !$activationKey) {
			return $app->redirect('login');
		}
		//create reset password form
		$form = $app->form('reset', [
			'method' => 'POST',
			'model' => $app->auth->model(),
			'onSuccess' => function($values, &$errors, &$message) use($app, $userId, $activationKey) {
				//can reset?
				if(!$app->auth->resetPwd($userId, $activationKey, $values['password'])) {
					$errors[] = 'This password reset link has expired. Please request a new <a href="' . $app->url('forgot') . '">password reset</a>.';
					return false;
				}
				//redirect to login
				$app->redirect('login', [
					'query' => [
						'msg' => 'Your password has been successfully updated.',
					],
				]);
			},
		]);
		//add form fields
		$form->password('password', [ 'validate' => 'length(8,30)', 'override' => true ]);
		$form->password('password_confirm', [ 'validate' => 'equals(POST.password)' ]);
		$form->captcha('Verify');
		$form->submit('Confirm new password');
		//return
		return $form;
	}

}