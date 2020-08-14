<?php

namespace Bforum\Model;

class ForumMessage extends \Bstage\Orm\Model {

	protected $id = 0;
	protected $text = '';
	protected $status = 0;
	protected $dateCreated = '';

	protected $user;
	protected $topic;

}