<?php

namespace Bstage\Security;

class Validator {

	protected $rules = [];
	protected $errors = [];
	protected $lastErrorField = '';

	protected $field = '';
	protected $label = '';

	protected $db;
	protected $crypt;
	protected $captcha;

	protected static $globalRules = [];

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			//is static property?
			if(in_array($k, [ 'globalRules' ])) {
				self::$$k = $v;
			} else if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function clone() {
		return clone($this)->reset();
	}

	public function reset() {
		//reset vars
		$this->field = '';
		$this->label = '';
		$this->errors = [];
		//chain it
		return $this;
	}

	public function getErrors($field=null) {
		//one field?
		if($field) {
			return isset($this->errors[$field]) ? $this->errors[$field] : null;
		}
		//all fields
		return $this->errors;
	}

	public function addError($message, $field=null) {
		//set vars
		$field = $field ?: $this->field;
		$label = $this->label ?: $field;
		//create array?
		if(!isset($this->errors[$field])) {
			$this->errors[$field] = [];
		}
		//add labe to message
		$message = str_replace(':label', str_replace('_', ' ', $label), $message);
		$message = ucfirst(trim($message));
		//add error?
		if(!in_array($message, $this->errors[$field])) {
			$this->errors[$field][] = $message;
		}
		//cache last error field
		$this->lastErrorField = $field;
		//return
		return $this->errors;
	}

	public function addRule($rule, $callback, $global=true) {
		//add rule
		if($global) {
			self::$globalRules[$rule] = $callback;
		} else {
			$this->rules[$rule] = $callback;
		}
		//chain it
		return $this;
	}

	public function validate($value, $rules, array $opts=[]) {
		//format opts
		$opts = array_merge([
			'field' => null,
			'label' => null,
			'filter' => false,
			'reset' => false,
			'stop' => true,
		], $opts);
		//rules to array?
		if(!is_array($rules)) {
			$rules = array_map('trim', explode('|', $rules));
		}
		//reset state?
		if($opts['reset']) {
			$this->reset();
		}
		//cache field
		$this->field = $opts['field'];
		$this->label = $opts['label'];
		//is optional?
		if(in_array('optional', $rules)) {
			if($value) {
				$rules = array_filter($rules, function($item) { return $item !== 'optional'; });
			} else {
				$rules = [];
			}
		}
		//loop through rules
		foreach($rules as $rule) {
			//set vars
			$cb = null;
			$params = [];
			//has params?
			if(preg_match('/^(.*)\((.*)\)$/', $rule, $match)) {
				$rule = $match[1];
				$params = array_map('trim', explode(',', $match[2]));
			}
			//find callback
			if(isset($this->rules[$rule])) {
				$cb = $this->rules[$rule];
			} else if(isset(self::$globalRules[$rule])) {
				$cb = self::$globalRules[$rule];
			} else if(method_exists($this, '_rule' . ucfirst($rule))) {
				$cb = [ $this, '_rule' . ucfirst($rule) ];
			} else if(is_callable($rule)) {
				$cb = $rule;
			}
			//valid callback?
			if(!$cb || !is_callable($cb)) {
				throw new \Exception($rule . ' rule does not exist');
			}
			//execute callback
			$res = $this->execCallback($cb, $value, $params, $opts['filter']);
			//update input?
			if($opts['filter']) {
				$value = $res;
			}
			//stop here?
			if($opts['stop'] && !$opts['filter'] && $this->lastErrorField === $opts['field']) {
				break;
			}
		}
		//return
		return $opts['filter'] ? $value : empty($this->errors);
	}

	public function filter($value, $rules='xss', array $opts=[]) {
		//filter flag
		$opts['filter'] = true;
		//return
		return $this->validate($value, $rules, $opts);
	}

	protected function execCallback($cb, $value, $params, $isFilter) {
		//is array?
		if(!is_array($value)) {
			return call_user_func($cb, $value, $params, $isFilter, $this);
		}
		//loop through array
		foreach($value as $k => $v) {
			$value[$k] = $this->execCallback($cb, $v, $params, $isFilter);
		}
		//return
		return $value;
	}

	protected function _ruleRequired($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return $value;
		}
		//validation failed?
		if(empty($value)) {
			return $this->addError(':label is required'); 
		}
		//passed
		return true;
	}

	protected function _ruleRegex($value, array $params, $isFilter) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Regex parameter required');
		}
		//set vars
		$pattern = '/' . preg_quote($params[0], '/') . '/';
		//filter input?
		if($isFilter) {
			return preg_replace($pattern, '', $value);
		}
		//validation failed?
		if(!preg_match($pattern, $value)) {
			return $this->addError(':label must match regex pattern'); 
		}
		//passed
		return true;
	}

	protected function _ruleInt($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return intval($value);
		}
		//validation failed?
		if((string) $value !== (string) intval($value)) {
			return $this->addError(':label must be an integer'); 
		}
		//passed
		return true;
	}

	protected function _ruleNumeric($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return preg_replace('/[^\+\-\.0-9]/', '', $value);
		}
		//validation failed?
		if(!is_numeric($value)) {
			return $this->addError(':label must be a number'); 
		}
		//passed
		return true;
	}

	protected function _ruleAlphanumeric($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return preg_replace('/[^a-z0-9]/i', '', $value);
		}
		//validation failed?
		if(!preg_match('/^[a-z0-9]+$/i', $value)) {
			return $this->addError(':label must only contain letters and numbers'); 
		}
		//passed
		return true;
	}

	protected function _ruleUuid($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return preg_replace('/[^a-f0-9\-]/i', '', $value);
		}
		//validation failed?
		if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89AB][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) {
			return $this->addError(':label must be a UUID'); 
		}
		//passed
		return true;
	}

	protected function _ruleBool($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return !!$value;
		}
		//validation failed?
		if($value !== (bool) $value) {
			return $this->addError(':label must be a boolean'); 
		}
		//passed
		return true;
	}

	protected function _ruleNull($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return null;
		}
		//validation failed?
		if($value !== null) {
			return $this->addError(':label must be null'); 
		}
		//passed
		return true;
	}

	protected function _ruleEmail($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return filter_var($value, FILTER_SANITIZE_EMAIL);
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_EMAIL)) {
			return $this->addError(':label must be a valid email address'); 
		}
		//passed
		return true;
	}

	protected function _rulePhone($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			//remove optional characters
			$value = str_replace('-', '', $value);
			$value = preg_replace('/\s+/', '', $value);
			//check country code
			$cc = null;
			$hasCode = strpos($value, '+') === 0;
			//guess country code?
			if($cc === null) {
				//is uk?
				if(strpos($value, '07') === 0 && strlen($value) == 11) {
					$cc = '+44';
				}
			}	
			//add country code?
			if(!$hasCode && $cc) {
				$value = $cc . preg_replace('/^0+/', '', $value);
			}
			//return
			return $value;
		}
		//set strict flag
		$strict = in_array('cc', $params) ? '' : '?';
		//valid format?
		if(!preg_match('/^\+' . $strict . '[0-9]{6,14}$/', $value)) {
			return $this->addError(':label must be a valid phone number'); 
		}
		//passed
		return true;
	}

	protected function _rulePhoneOrEmail($value, array $params, $isFilter) {
		//set vars
		$method = '_rulePhone';
		//looks like email?
		if(strpos($value, '@') !== false || !preg_match('/[0-9]/', $value)) {
			$method = '_ruleEmail';
		}
		//is empty?
		if(empty($value)) {
			return $isFilter ? '' : $this->addError(':label must be a valid email address or phone number'); 
		}
		//return
		return $this->$method($value, $params, $isFilter);
	}

	protected function _ruleUrl($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return rtrim(filter_var($value, FILTER_SANITIZE_URL), '/');
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_URL)) {
			return $this->addError(':label must be a valid URL'); 
		}
		//passed
		return true;
	}

	protected function _ruleIp($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			return preg_replace('/[^\.0-9]/', '', $value);
		}
		//validation failed?
		if(!filter_var($value, FILTER_VALIDATE_IP)) {
			return $this->addError(':label must be a valid IP address'); 
		}
		//passed
		return true;
	}

	protected function _ruleLength($value, array $params, $isFilter) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Min,Max length parameters required');
		}
		//filter input?
		if($isFilter) {
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

	protected function _ruleRange($value, array $params, $isFilter) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Min,Max numeric range parameters required');
		}
		//filter input?
		if($isFilter) {
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

	protected function _ruleList($value, array $params, $isFilter) {
		//filter input?
		if($isFilter) {
			//split by common delims?
			if(is_string($value)) {
				$value = preg_split("/(,|;|\s+)/", $value);
			}
			//final delim
			$delim = (isset($params[0]) && strlen($params[0]) > 0) ? $params[0] : ',';
			//reassemble list
			$value = implode($delim, array_map('trim', $value));
			$value = preg_replace('/' . $delim . '+/', $delim, $value);
			//return
			return trim($value, $delim);
		}
		//validate input
		return is_string($value);
	}

	protected function _ruleXss($value, array $params, $isFilter) {
		//decode input
		$value = rawurldecode(rawurldecode($value));
		//run unsafe check
		$unsafe = preg_replace('/\s+/', '', $value); 
		$unsafe = preg_match('/(onclick|onload|onerror|onmouse|onkey)|(script|alert|confirm)[\:\>\(]/iS', $unsafe);
		//filter input?
		if($isFilter) {
			return $unsafe ? '' : filter_var($value, FILTER_SANITIZE_STRING);
		}
		//validation failed?
		if($value !== filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)) {
			return $this->addError(':label contains unsafe characters'); 
		}
		//passed
		return true;
	}

	protected function _ruleEquals($value, array $params, $isFilter) {
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Equals field parameter required');
		}
		//filter input?
		if($isFilter) {
			return $value;
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
		//validation failed?
		if($value !== $equals) {
			return $this->addError(':label does not match ' . $attr); 
		}
		//passed
		return true;
	}

	protected function _ruleUnique($value, array $params, $isFilter) {
		//db set?
		if(!$this->db) {
			throw new \Exception('Database object not set');
		}
		//required params passed?
		if(count($params) < 1) {
			throw new \Exception('Database field parameter required');
		}
		//filter input?
		if($isFilter) {
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
		$where = [ $column => $value ];
		//add params
		foreach($params as $p) {
			if(substr($p, -1) !== '=') {
				$where[] = $p;
			}
		}
		//does value exist?
		if($value && $this->db->select($table, [ 'where' => $where, 'limit' => 1 ])) {
			return $this->addError(':label already exists'); 
		}
		//success
		return true;
	}

	protected function _ruleCaptcha($value, array $params, $isFilter) {
		//captcha set?
		if(!$this->captcha) {
			throw new \Exception('Captcha object not set');
		}
		//filter input?
		if($isFilter) {
			return $value;
		}
		//validation failed?
		if(!$this->captcha->isValid($value)) {
			return $this->addError(':label does not match'); 
		}
		//success
		return true;
	}

	protected function _ruleHashPwd($value, array $params, $isFilter) {
		//crypt set?
		if(!$this->crypt) {
			throw new \Exception('Crypt object not set');
		}
		//build hash
		$hash = $this->crypt->hashPwd($value);
		//filter input?
		if($isFilter) {
			return $hash;
		}
		//return
		return $hash === $value;
	}

}