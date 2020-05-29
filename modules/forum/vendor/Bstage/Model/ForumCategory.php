<?php

namespace Bstage\Model;

class ForumCategory extends \Bstage\Orm\AbstractModel {

	protected $id = 0;
	protected $name = '';
	protected $position = 0;
	protected $status = 0;

	protected $topicCount = 0;

}