<?php

namespace Bstage\Http;

class Input {

	protected $validator = null;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//JIT check
		isset($_ENV) && isset($_SERVER) && isset($_REQUEST);
	}

	public function __call($name, array $args) {
		//set vars
		$opts = [];
		$param = isset($args[0]) ? $args[0] : null;
		//format opts?
		if(count($args) > 1) {
			if(!is_array($args[1]) || isset($args[1][0])) {
				$opts = array( 'default' => $args[1] );
			} else {
				$opts = $args[1];
			}
		}
		//check globals
		return $this->find($name, $param, $opts);
	}

	public function getValidator() {
		return $this->validator;
	}

	public function has($global, $param=null) {
		//format global
		$global = '_' . trim(strtoupper($global), '_');
		//global only?
		if($param === null) {
			return isset($GLOBALS[$global]);
		}
		//check param
		return isset($GLOBALS[$global]) && isset($GLOBALS[$global][$param]);
	}

	public function find($global, $param=null, array $opts=[]) {
		//set defaults
		$opts = array_merge([
			'field' => null,
			'label' => null,
			'default' => null,
			'validate' => null,
			'filter' => null,
		], $opts);
		//set vars
		$global = '_' . trim(strtoupper($global), '_');
		$param = str_replace([ '*', '..*' ], '.*', $param);
		$default = ($opts['default'] === null) ? ($param ? '' : []) : $opts['default'];
		$validOpts = [ 'field' => $opts['field'] ?: $param, 'label' => $opts['label'] ];
		//find global
		if($global === '_' || $global === '_REQUEST') {
			//$_POST takes priority
			$data = array_merge($_GET, $_POST);
		} else {
			//global exists?
			if(isset($GLOBALS[$global]) && is_array($GLOBALS[$global])) {
				$data = $GLOBALS[$global];
			} else {
				return $default;
			}
		}
		//filter data
		if($param && strpos($param, '.*') === false) {
			//single param
			$data = isset($data[$param]) ? $data[$param] : $default;
		} else if($param) {
			//wildcard param
			foreach($data as $k => $v) {
				if(!preg_match('/' . $param . '/', $k)) {
					unset($data[$k]);
				}
			}
		}
		//filter?
		if($opts['filter']) {
			$data = $this->validator->filter($data, $opts['filter'], $validOpts);
		}
		//validate?
		if($opts['validate']) {
			$this->validator->validate($data, $opts['validate'], $validOpts);
		}
		//return
		return $data;
	}

}