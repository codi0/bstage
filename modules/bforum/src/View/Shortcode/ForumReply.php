<?php

namespace Bforum\View\Shortcode;

class ForumReply {

	public function parse(array $params, $app) {
		//is signed in?
		if(!$app->auth->id()) {
			return '<p>Please <a href="' . $app->url('login') . '">sign in</a> to continue.</p>';
		}
		//is activated?
		if(!$app->auth->isActive()) {
			return '<p>You must verify your email address to continue. <a href="' . $app->url('account') . '">Resend activation email</a>.</p>';
		}
		//create model
		$message = $app->model('forumMessage', [
			'data' => [
				'topic' => $params['topic'],
				'user' => $app->auth,
			],
		]);
		//create form
		$form = $app->form('edit-profile', [
			'method' => 'POST',
			'model' => $message,
			'onSuccess' => function() use($app) {
				return $app->url(null, []) . '/latest?replied';
			},
		]);
		//add fields
		$form->textarea('text', [ 'label' => '' ]);
		$form->submit('Submit');
		//return
		return $form;
	}

}