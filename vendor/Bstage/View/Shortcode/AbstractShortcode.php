<?php

namespace Bstage\View\Shortcode;

abstract class AbstractShortcode {

	protected $app;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __invoke(array $params) {
		return $this->parse($params);
	}

	abstract public function parse(array $params);

}