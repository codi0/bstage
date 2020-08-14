<?php

namespace Bforum;

class Module extends \Bstage\App\Module {

	protected $version = '1.0.0';

	protected $requires = [
		'bauth' => [],
	];

	protected function init() {
		//register routes
		$this->app->route('/forum/<:id?>', 'Bforum\Controller\ListTopics');
		$this->app->route('/forum/add/<:id?>', 'Bforum\Controller\AddTopic');
		$this->app->route('/forum/topic/<:id>', 'Bforum\Controller\ViewTopic');
		$this->app->route('/forum/topic/<:id>/latest', 'Bforum\Controller\LatestTopicPage');
		//register services
		$this->app->service('forum', function(array $opts, $app) {
			return new \Bforum\Service\Forum(array_merge([
				'app' => $app,
			], $opts));
		});
	}

}