<?php

namespace Bstage\Model;

class ForumTopic extends \Bstage\Orm\AbstractModel {

	protected $id = 0;
	protected $title = '';
	protected $status = 0;
	protected $dateCreated = '';

	protected $messageCount = 0;

	protected $user;
	protected $category;
	protected $messages = [];

}