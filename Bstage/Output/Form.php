<?php

namespace Bstage\Output;

class Form {

	protected $name = '';
	protected $model = null;
	protected $attr = array();
	protected $fields = array();
	protected $errors = array();
	protected $values = array();

	protected $isValid = null;
	protected $onSuccess = null;

	protected $html = null;
	protected $input = null;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __toString() {
		return $this->render();
	}

	public function __get($name) {
		return $this->create($name);
	}

	public function create($name, $method='post', $action='') {
		//factory cache
		static $factory = array();
		//form exists?
		if(!isset($factory[$name])) {
			//set vars
			$opts = array();
			//method is array?
			if(is_array($method)) {
				$opts = $method;
				$method = 'post';
			}
			//set attr array
			$opts['attr'] = isset($opts['attr']) ? $opts['attr'] : array();
			//set core attrs
			foreach(array( 'name' => $name, 'method' => $method, 'action' => $action, 'id' => $name . 'Form' ) as $k => $v) {
				//attr already set?
				if(isset($opts[$k]) && $opts[$k]) {
					$opts['attr'][$k] = $opts[$k];
					unset($opts[$k]);
				} elseif(!isset($opts['attr'][$k]) || !$opts['attr'][$k]) {
					$opts['attr'][$k] = $v;
				}
			}
			//lowercase method
			$opts['attr']['method'] = strtolower($opts['attr']['method']);
			//set name
			$opts['name'] = $name;
			//set required objects
			$opts['html'] = $this->html;
			$opts['input'] = $this->input;
			//get class
			$class = get_class($this);
			//create form
			$factory[$name] = new $class($opts);
		}
		//return
		return $factory[$name];
	}

	public function hydrate($data) {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//set data
		$this->model = $data;
		//chain it
		return $this;
	}

	public function attr($key, $val=null) {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//is array?
		if(is_array($key)) {
			$this->attr = array_merge($this->attr, $key);
		} elseif($val !== null) {
			$this->attr[$key] = $val;
		}
		//chain it
		return $this;
	}

	public function input($name, array $config=array()) {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//add field
		$this->fields[$name] = $config;
		//chain it
		return $this;
	}

	public function textarea($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'textarea' ), $config));
	}

	public function hidden($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'hidden', 'label' => '' ), $config));
	}

	public function password($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'password' ), $config));
	}

	public function file($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'file' ), $config));
	}

	public function select($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'select' ), $config));
	}

	public function checkbox($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'checkbox' ), $config));
	}

	public function radio($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'radio' ), $config));
	}

	public function button($name, array $config=array()) {
		return $this->input($name, array_merge(array( 'type' => 'button' ), $config));
	}

	public function captcha($label, array $config=array()) {
		return $this->input('captcha', array_merge(array( 'type' => 'captcha', 'label' => $label, 'validate' => 'captcha' ), $config));
	}

	public function submit($value, array $config=array()) {
		return $this->input('submit', array_merge(array( 'type' => 'submit', 'value' => $value, 'label' => '' ), $config));
	}

	public function isValid() {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//already run?
		if($this->isValid !== null) {
			return $this->isValid;
		}
		//set vars
		$values = array();
		$modelMeta = array();
		$method = $this->attr['method'] === 'get' ? 'get' : 'post';
		$hasModel = is_object($this->model);
		$modelId = $this->input->$method('id');
		$firstField = array_keys($this->fields)[0];
		//reset errors
		$this->errors = array();
		//method matched?
		if($method !== strtolower($_SERVER['REQUEST_METHOD'])) {
			return null;
		}
		//form submitted?
		if(!$this->input->has($method, $firstField)) {
			return null;
		}
		//model matched?
		if((!$hasModel && $modelId) || ($hasModel && $this->model->id != $modelId)) {
			return null;
		}
		//get model meta data?
		if($hasModel && method_exists($this->model, 'toArray')) {
			$modelMeta = $this->model->toArray([ 'key' => null, 'underscore' => true ]);
		}
		//loop through fields
		foreach($this->fields as $name => $opts) {
			//is submit field?
			if(isset($opts['type']) && $opts['type'] === 'submit') {
				continue;
			}
			//set vars
			$validate = $sanitize = [];
			//setup validators
			foreach(array( 'validate', 'sanitize' ) as $k) {
				//add model validator?
				if(isset($modelMeta[$name]) && $modelMeta[$name][$k]) {
					$tmp = is_array($modelMeta[$name][$k]) ? $modelMeta[$name][$k] : explode('|', $modelMeta[$name][$k]);
					$$k = array_merge($$k, $tmp);
				}
				//add custom validator?
				if(isset($opts[$k]) && $opts[$k]) {
					$tmp = is_array($opts[$k]) ? $opts[$k] : explode('|', $opts[$k]);
					$$k = array_merge($$k, $tmp);
				}
			}
			//get value
			$values[$name] = $this->input->$method($name, array(
				'field' => $name,
				'label' => isset($opts['label']) ? $opts['label'] : '',
				'validate' => $validate,
				'sanitize' => $sanitize ?: [ 'xss' ],
				'format' => isset($opts['format']) ? $opts['format'] : null,
			));
			//get errors
			$this->errors = $this->input->getValidator()->mergeErrors($this->errors);
		}
		//set vars
		$res = $id = '';
		//cache values
		$this->values = $values;
		//set flag
		$this->isValid = empty($this->errors);
		//success callback?
		if($this->isValid && $this->onSuccess) {
			//execute callback
			$cb = $this->onSuccess;
			$res = $cb($this->values, $this->errors);
			//still valid?
			if($res === false || $this->errors) {
				$this->isValid = false;
			}
		}
		//save model?
		if($this->isValid && $hasModel) {
			//loop through values
			foreach($this->values as $key => $val) {
				//does property exist?
				if(isset($this->model->$key)) {
					$this->model->$key = $val;
				}
			}
			//save model
			$id = $this->model->save();
			$this->errors = $this->model->errors();
			//save failed?
			if($this->errors || $id === false) {
				$res = $this->isValid = false;
			}
		}
		//redirect user?
		if($res && filter_var($res, FILTER_VALIDATE_URL)) {
			//format url
			$url = str_replace([ '{id}', urlencode('{id}') ], $id, $res);
			//headers sent?
			if(headers_sent()) {
				echo '<meta http-equiv="refresh" content="0;url=' . $url . '">';
			} else {
				header('Location: ' . $url);
			}
			//stop
			exit();
		}
		//return
		return $this->isValid;
	}

	public function errors() {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//return
		return $this->errors;
	}

	public function values() {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//return
		return $this->values;
	}

	public function init() {
		//validate
		$this->isValid();
		//chain it
		return $this;
	}

	public function render() {
		//form set?
		if(!$this->name) {
			throw new \Exception("No form created");
		}
		//init
		$this->init();
		//set vars
		$html = '';
		$formAttr = array();
		$hasModel = is_object($this->model);
		//loop through form attributes
		foreach($this->attr as $k => $v) {
			if(strlen($v) > 0) {
				$formAttr[] = $k . '="' . $v . '"';
			}
		}
		//open form
		$html .= '<form ' . implode(' ', $formAttr) . '>' . "\n";
		//show success message?
		if($this->isValid || (is_null($this->isValid) && $this->input->get('success') == 'true')) {
			$html .= '<div class="notice updated">Form successfully saved</div>' . "\n";
		}
		//show error summary?
		if($this->errors && count($this->fields) > 10) {
			$html .= '<div class="notice error">Please review the errors below to continue:</div>' . "\n";
		}
		//show non field errors?
		foreach($this->errors as $key => $val) {
			//is field error?
			if(isset($this->fields[$key])) {
				continue;
			}
			//loop through errors
			foreach((array) $val as $v) {
				$html .= '<div class="notice error">' . $v . '</div>' . "\n";
			}
		}
		//add ID field?
		if($this->model && $this->model->id) {
			//get ID
			$id = $hasModel ? $this->model->id : (isset($this->model['id']) ? $this->model['id'] : 0);
			//has ID?
			if($id > 0) {
				$html .= '<input type="hidden" name="id" value="' . $id . '">' . "\n";
			}
		}
		//create fields
		foreach($this->fields as $name => $opts) {
			//set vars
			$field = '';
			//format opts
			$opts = array_merge([
				'name' => $name,
				'type' => 'text',
				'value' => '',
				'placeholder' => '',
				'label' => str_replace('_', ' ', ucfirst($name)),
				'error' => isset($this->errors[$name]) ? $this->errors[$name] : [],
			], $opts);
			//update value?
			if(isset($this->values[$name])) {
				//use input
				$opts['value'] = $this->values[$name];
			} elseif($this->model) {
				//use model
				if($hasModel) {
					$tmp = isset($this->model->$name) ? $this->model->$name : '';
				} else {
					$tmp = isset($this->model[$name]) ? $this->model[$name] : '';
				}
				//set value?
				if($tmp || $tmp === 0 || $tmp === '0') {
					$opts['value'] = $tmp;
				}
			}
			//format value?
			if(isset($opts['format']) && $opts['format']) {
				$opts['value'] = call_user_func($opts['format'], $opts['value'], true);
			}
			//add label?
			if(!empty($opts['label'])) {
				$field .= '<label for="' . $opts['name'] . '">' . $opts['label'] . '</label>' . "\n";
			}
			//open input wrapper
			$field .= '<div class="input">' . "\n";
			//custom render?
			if(isset($opts['html']) && $opts['html']) {
				$field .= is_callable($opts['html']) ? call_user_func($opts['html'], $opts) : $opts['html'];
			} else {
				$method = $opts['type'];
				$attr = $this->formatAttr($opts);
				$field .= $this->html->$method($opts['name'], $opts['value'], $attr);
			}
			//add html after?
			if(isset($opts['after']) && $opts['after']) {
				$field .= $opts['after'];
			}
			//trim output
			$field = trim($field) . "\n";
			//close input wrapper
			$field .= '</div>' . "\n";
			//format classes
			$classes  = 'field ' . $opts['type'];
			$classes .= ($opts['type'] !== $name) ? ' ' . $name : '';
			$classes .= $opts['error'] ? ' has-error' : '';
			$classes .= (stripos($field, '<label') === false) ? ' no-label' : '';
			//open field wrapper?
			if($opts['type'] !== 'hidden') {
				$html .= '<div class="' . str_replace('_', '-', $classes) . '">' . "\n";
			}
			//add field
			$html .= trim($field) . "\n";
			//display any errors
			foreach((array) $opts['error'] as $error) {
				$html .= '<div class="notice error">' . $error . '</div>' . "\n";
			}
			//close field wrapper
			if($opts['type'] !== 'hidden') {
				$html .= '</div>' . "\n";
			}
		}
		//close form
		$html .= '</form>';
		//return
		return $html;
	}

	protected function formatAttr(array $attr) {
		//attrs to remove
		$remove = [ 'name', 'value', 'label', 'error', 'validate', 'sanitize', 'format', 'before', 'after' ];
		//loop through attributes
		foreach($attr as $key => $val) {
			if(!is_scalar($val) || in_array($key, $remove)) {
				unset($attr[$key]);
			}
		}
		//return
		return $attr;
	}

}