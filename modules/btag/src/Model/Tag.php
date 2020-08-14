<?php

namespace Btag\Model;

class Tag extends \Bstage\Orm\Model {

	protected $id = 0;
	protected $name = '';
	protected $taxonomy = 'tag';
	protected $parentId = 0;
	protected $order = 0;
	protected $status = 0;

}