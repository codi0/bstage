<?php

namespace Bstage\Security;

class Validator {

	protected $field = '';
	protected $label = '';
	protected $errors = array();

	protected $db = null;
	protected $captcha = null;

	protected static $rules = array();

	public function __construct(array $opts=array()) {
		//static props
		$statProps = array( 'rules' );
		//loop through opts
		foreach($opts as $k => $v) {
			//set property?
			if(property_exists($this, $k)) {
				//is static property?
				if(in_array($k, $statProps)) {
					self::$$k = $v;
				} else {
					$this->$k = $v;
				}
			}
		}
	}

	public function getErrors() {
		return $this->errors;
	}

	public function addError($message) {
		//create array?
		if(!isset($this->errors[$this->field])) {
			$this->errors[$this->field] = array();
		}
		//add labe to message
		$message = str_replace(':label', str_replace('_', ' ', $this->label), $message);
		$message = ucfirst(trim($message));
		//add error?
		if(!in_array($message, $this->errors[$this->field])) {
			$this->errors[$this->field][] = $message;
		}
		//chain it
		return $this;
	}

	public function mergeErrors(array $errors) {
		//loop through cached errors
		foreach($this->errors as $field => $err) {
			//create array?
			if(!isset($errors[$field])) {
				$errors[$field] = array();
			}
			//add errors
			foreach($err as $e) {
				$errors[$field][] = $e;
			}
		}
		//return
		return $errors;
	}

	public function addRule($rule, $callback) {
		//add rule
		self::$rules[$rule] = $callback;
		//chain it
		return $this;
	}

	public function isValid($value, $rules, array $opts=array()) {
		//format opts
		$opts = array_merge(array(
			'field' => null,
			'label' => null,
			'sanitize' => false,
			'stop' => true,
		), $opts);
		//rules to array?
		if(!is_array($rules)) {
			$rules = array_map('trim', explode('|', $rules));
		}
		//cache field
		$this->field = $opts['field'];
		$this->label = $opts['label'] ?: $opts['field'];
		//reset errors?
		if(!$opts['sanitize']) {
			$this->errors = array();
		}
		//loop through rules
		foreach($rules as $rule) {
			//set vars
			$cb = null;
			$params = array();
			//has params?
			if(preg_match('/^(.*)\((.*)\)$/', $rule, $match)) {
				$rule = $match[1];
				$params = array_map('trim', explode(',', $match[2]));
			}
			//is optional?
			if($rule === 'optional') {
				if($value) {
					continue;
				} else {
					break;
				}
			}
			//find callback
			if(isset(self::$rules[$rule])) {
				$cb = self::$rules[$rule];
			} elseif(method_exists($this, '_rule' . ucfirst($rule))) {
				$cb = array( $this, '_rule' . ucfirst($rule) );
			} elseif(function_exists($rule)) {
				$cb = $rule;
			}
			//valid callback?
			if(!$cb || !is_callable($cb)) {
				throw new \Exception($rule . ' rule does not exist');
			}
			//execute callback
			$res = $this->execCallback($cb, $value, $params, $opts['sanitize']);
			//update input?
			if($opts['sanitize']) {
				$value = $res;
			}
			//stop here?
			if($opts['stop'] && !$opts['sanitize'] && $this->errors) {
				break;
			}
		}
		//return
		return $opts['sanitize'] ? $value : empty($this->errors);
	}

	public function sanitize($value, $rules, array $opts=array()) {
		//sanitize flag
		$opts['sanitize'] = true;
		//return
		return $this->isValid($value, $rules, $opts);
	}

	protected function execCallback($cb, $value, $params, $sanitize) {
		//is array?
		if(!is_array($value)) {
			return call_user_func($cb, $value, $params, $sanitize, $this);
		}
		//loop through array
		foreach($value as $k => $v) {
			$value[$k] = $this->execCallback($cb, $v, $params, $sanitize);
		}
		//return
		return $value;
	}

	protected function _ruleRequired($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//validation failed?
		if(empty($value)) {
			return $this->addError(':label is required'); 
		}
		//passed
		return true;
	}

	protected function _ruleRegex($value, array $params, $sanitize) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Regex parameter required');
		}
		//set vars
		$pattern = '/' . preg_quote($params[0], '/') . '/';
		//sanitize input?
		if($sanitize) {
			return preg_replace($pattern, '', $value);
		}
		//validation failed?
		if(!preg_match($pattern, $value)) {
			return $this->addError(':label must match regex pattern'); 
		}
		//passed
		return true;
	}

	protected function _ruleInt($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return intval($value);
		}
		//validation failed?
		if((string) $value !== (string) intval($value)) {
			return $this->addError(':label must be an integer'); 
		}
		//passed
		return true;
	}

	protected function _ruleNumeric($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return preg_replace('/[^\+\-\.0-9]/', '', $value);
		}
		//validation failed?
		if(!is_numeric($value)) {
			return $this->addError(':label must be a number'); 
		}
		//passed
		return true;
	}

	protected function _ruleBool($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return (bool) $value;
		}
		//validation failed?
		if($value !== (bool) $value) {
			return $this->addError(':label must be a boolean'); 
		}
		//passed
		return true;
	}

	protected function _ruleNull($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return null;
		}
		//validation failed?
		if($value !== null) {
			return $this->addError(':label must be null'); 
		}
		//passed
		return true;
	}

	protected function _ruleEmail($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return filter_var($value, FILTER_SANITIZE_EMAIL);
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
			return $this->addError(':label must be a valid email address'); 
		}
		//passed
		return true;
	}

	protected function _ruleUrl($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return rtrim(filter_var($value, FILTER_SANITIZE_URL), '/');
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_URL)) {
			return $this->addError(':label must be a valid URL'); 
		}
		//passed
		return true;
	}

	protected function _ruleIp($value, array $params, $sanitize) {
		//sanitize input?
		if($sanitize) {
			return preg_replace('/[^\.0-9]/', '', $value);
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_IP)) {
			return $this->addError(':label must be a valid IP address'); 
		}
		//passed
		return true;
	}

	protected function _ruleLength($value, array $params, $sanitize) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Min,Max length parameters required');
		}
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//set max as min?
		if(!isset($params[1])) {
			$params[1] = $params[0];
		}
		//min length failed?
		if(strlen($value) < $params[0]) {
			return $this->addError(':label must be at least ' . $params[0] . ' characters'); 
		}
		//max length failed?
		if(strlen($value) > $params[1]) {
			return $this->addError(':label must be no more than ' . $params[1] . ' characters'); 
		}
		//passed
		return true;
	}

	protected function _ruleRange($value, array $params, $sanitize) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Min,Max numeric range parameters required');
		}
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//set max as min?
		if(!isset($params[1])) {
			$params[1] = $params[0];
		}
		//min range failed?
		if($value < $params[0]) {
			return $this->addError(':label must be at least ' . $params[0]); 
		}
		//max range failed?
		if($value > $params[1]) {
			return $this->addError(':label must be no more than ' . $params[1]); 
		}
		//passed
		return true;
	}

	protected function _ruleXss($value, array $params, $sanitize) {
		//set vars
		$unsafe = preg_match('/(onclick|onload|onerror|onmouse|onkey)|(script|alert|confirm)[\:\>\(]/iS', $value);
		$sanitized = trim(filter_var(rawurldecode($value), FILTER_SANITIZE_STRING));
		//sanitize input?
		if($sanitize) {
			return $unsafe ? '' : $sanitized;
		}
		//validation failed?
		if($sanitized !== $value) {
			return $this->addError(':label contains unsafe characters'); 
		}
		//passed
		return true;
	}

	protected function _ruleEquals($value, array $params, $sanitize) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Equals field parameter required');
		}
		//is global?
		if(strpos($params[0], '.') !== false) {
			//parse global
			list($global, $attr) = explode('.', $params[0], 2);
			//format global identifier
			$global = '_' . strtoupper(trim($global, '_'));
			//global attr found?
			if(isset($GLOBALS[$global]) && isset($GLOBALS[$global][$attr])) {
				$equals = $GLOBALS[$global][$attr];
			} else {
				$equals = '';
			}
		} else {
			//use raw param
			$equals = $attr = $params[0];
		}
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//validation failed?
		if($value !== $equals) {
			return $this->addError(':label does not match ' . $attr); 
		}
		//passed
		return true;
	}

	protected function _ruleUnique($value, array $params, $sanitize) {
		//db set?
		if(!$this->db) {
			throw new \Exception('Database object not set');
		}
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Database table.field parameter required');
		}
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//get table name
		$exp = array_shift($params);
		$exp = explode('.', $exp, 2);
		$table = trim($exp[0]);
		//get column name
		if(isset($exp[1]) && $exp[1]) {
			$column = trim($exp[1]);
		} else {
			$column = $this->field;
		}
		//valid input?
		if(!$table || !$column) {
			throw new \Exception('Invalid table and column for unqiue validator');
		}
		//build where clause
		$where = array( $column => $value );
		//add params
		foreach($params as $p) {
			if(substr($p, -1) !== '=') {
				$where[] = $p;
			}
		}
		//does value exist?
		if($value && $this->db->select($table, $where, 1)) {
			return $this->addError(':label already exists'); 
		}
		//success
		return true;
	}

	protected function _ruleCaptcha($value, array $params, $sanitize) {
		//captcha set?
		if(!$this->captcha) {
			throw new \Exception('Captcha object not set');
		}
		//sanitize input?
		if($sanitize) {
			return $value;
		}
		//validation failed?
		if(!$this->captcha->isValid($value)) {
			return $this->addError(':label does not match'); 
		}
		//success
		return true;
	}

}