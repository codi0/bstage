<?php

namespace Btag\Model\Mapper;

class Tag extends \Bstage\Orm\Mapper {

	protected $fields = [
		'id' => [
			'validate' => 'int',
			'save' => false,
		],
		'tag' => [
			'relation' => 'belongsTo',
			'model' => 'tag',
		],
	];

}