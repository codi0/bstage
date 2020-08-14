<?php

namespace Bauth\View\Shortcode;

class EditProfile {

	public function parse(array $params, $app) {
		//get model
		$user = $app->auth->model();
		//create form
		$form = $app->form('edit-profile', [
			'method' => 'POST',
			'model' => $user,
			'message' => 'Profile successfully updated',
		]);
		//add fields
		$form->input('username', [ 'validate' => "unique(user.username,id!=$user->id)" ]);
		$form->input('email', [ 'validate' => "unique(user.email,id!=$user->id)" ]);
		$form->password('password', [ 'value' => '', 'validate' => 'optional|length(8,30)', 'override' => true ]);
		$form->password('password_confirm', [ 'value' => '', 'validate' => 'equals(POST.password)' ]);
		$form = $this->customFields($form);
		$form->submit('Submit');
		//return
		return $form;
	}

	protected function customFields($form) {
		return $form;
	}

}