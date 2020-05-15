<?php

namespace Bstage\Http;

class Input {

	protected $validator = null;

	public function __construct(array $opts=array()) {
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
		$opts = array();
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

	public function find($global, $param=null, array $opts=array()) {
		//set opts
		$opts = array_merge(array(
			'field' => null,
			'label' => null,
			'default' => null,
			'validate' => null,
			'filter' => 'xss',
		), $opts);
		//current global?
		if(empty($global)) {
			$global = $_SERVER['REQUEST_METHOD'];
		}
		//set vars
		$res = array();
		$global = '_' . trim(strtoupper($global), '_');
		$param = str_replace(array( '*', '..*' ), '.*', $param);
		$default = ($opts['default'] === null) ? ($param ? '' : array()) : $opts['default'];
		$validOpts = array( 'field' => $opts['field'] ?: $param, 'label' => $opts['label'] );
		//run checks
		if(!isset($GLOBALS[$global]) || !is_array($GLOBALS[$global]) || !$param) {
			//all params
			$res = isset($GLOBALS[$global]) ? $GLOBALS[$global] : $default;
		} elseif(strpos($param, '.*') === false) {
			//single param
			$res = isset($GLOBALS[$global][$param]) ? $GLOBALS[$global][$param] : $default;
		} else {
			//wildcard param
			foreach($GLOBALS[$global] as $k => $v) {
				if(preg_match('/' . $param . '/', $k)) {
					$res[$k] = $v;
				}
			}
		}
		//filter?
		if($opts['filter']) {
			$res = $this->validator->filter($res, $opts['filter'], $validOpts);
		}
		//validate?
		if($opts['validate']) {
			$this->validator->validate($res, $opts['validate'], $validOpts);
		}
		//return
		return $res;
	}

}