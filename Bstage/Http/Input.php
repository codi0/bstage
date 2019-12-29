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
			'sanitize' => 'xss',
			'format' => null,
		), $opts);
		//set vars
		$res = array();
		$global = '_' . trim(strtoupper($global), '_');
		$param = str_replace(array( '*', '..*' ), '.*', $param);
		$default = ($opts['default'] === null) ? ($param ? '' : array()) : $opts['default'];
		$validOpts = array( 'field' => $opts['field'] ?: $param, 'label' => $opts['label'] );
		//return default?
		if(!isset($GLOBALS[$global]) || !is_array($GLOBALS[$global])) {
			return isset($GLOBALS[$global]) ? $GLOBALS[$global] : $default;
		}
		//run checks
		if(!$param) {
			//all params
			$res = $GLOBALS[$global];
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
		//format?
		if($opts['format']) {
			$res= call_user_func($opts['format'], $res, false);
		}
		//validate?
		if($opts['validate']) {
			$this->validator->isValid($res, $opts['validate'], $validOpts);
		}
		//sanitize?
		if($opts['sanitize']) {
			$res = $this->validator->sanitize($res, $opts['sanitize'], $validOpts);
		}
		//return
		return $res;
	}

}