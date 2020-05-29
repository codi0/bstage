<?php

namespace Bstage\View\Shortcode;

class EditProfile {

	public function parse(array $params, $app) {
		//get params
		$userId = $app->auth->id;
		//create form
		$form = $app->form('edit-profile', [
			'method' => 'POST',
			'model' => $app->auth,
			'message' => 'Profile successfully updated',
		]);
		//add fields
		$form->input('username', [ 'validate' => "unique(user.username,id!=$userId)" ]);
		$form->input('email', [ 'validate' => "unique(user.email,id!=$userId)" ]);
		$form->password('password', [ 'value' => '', 'validate' => 'optional|length(8,30)', 'override' => true ]);
		$form->password('password_confirm', [ 'value' => '', 'validate' => 'equals(POST.password)' ]);
		$form = $this->customFields($form);
		$form->submit('Update profile');
		//return
		return $form;
	}

	protected function customFields($form) {
		return $form;
	}

}