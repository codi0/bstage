<?php

namespace Bstage\View\Shortcode;

class ForumAddTopic {

	public function parse(array $params, $app) {
		//is signed in?
		if(!$app->auth->id) {
			return '<p>Please <a href="' . $app->url('login') . '">sign in</a> to continue.</p>';
		}
		//is activated?
		if(!$app->auth->isActive()) {
			return '<p>You must verify your email address to continue. <a href="' . $app->url('account') . '">Resend activation email</a>.</p>';
		}
		//set vars
		$catOpts = [ 0 => '-- select category --' ];
		//get categories
		$cats = $app->model('forumCategory', [
			'collection' => true,
			'query' => [
				'where' => [
					'status' => 1,
				],
				'order' => 'position ASC',
			],
		]);
		//format categories
		foreach($cats as $cat) {
			$catOpts[$cat->id] = $cat->name;
		}
		//create model
		$topic = $app->model('forumTopic');
		//create form
		$form = $app->form('edit-profile', [
			'method' => 'POST',
			'model' => $topic,
			'onSave' => function($values) use($app) {
				return [
					'title' => $values['title'],
					'category' => $values['category'],
					'user' => $app->auth,
					'messages' => [
						'topic' => '<SELF>',
						'text' => $values['text'],
						'user' => $app->auth,
					],
				];
			},
			'onSuccess' => function() use($app) {
				return $app->url('forum/topic/{id}');
			},
		]);
		//add fields
		$form->input('title');
		$form->select('category', [ 'options' => $catOpts, 'validate' => 'required|int' ]);
		$form->textarea('text', [ 'label' => 'Message', 'validate' => 'required', ]);
		$form->submit('Submit');
		//return
		return $form;
	}

}