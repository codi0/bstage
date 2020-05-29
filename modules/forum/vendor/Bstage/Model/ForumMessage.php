<?php

namespace Bstage\Model;

class ForumMessage extends \Bstage\Orm\AbstractModel {

	protected $id = 0;
	protected $text = '';
	protected $status = 0;
	protected $dateCreated = '';

	protected $user;
	protected $topic;

}