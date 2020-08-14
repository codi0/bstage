<?php

namespace Bstage\View\Form;

class Form {

	protected $name = '';
	protected $attr = [];
	protected $fields = [];
	protected $values = [];

	protected $model = [];
	protected $errors = [];
	protected $message = '';

	protected $isValid;
	protected $autosave = true;

	protected $onSave;
	protected $onSuccess;
	protected $onError;

	protected $orm;
	protected $html;
	protected $input;

	public function __construct($name, $method='post', $action='', array $opts=[]) {
		//method is array?
		if(is_array($method)) {
			$opts = $method;
			$method = 'post';
		}
		//set attr array
		$opts['attr'] = isset($opts['attr']) ? $opts['attr'] : [];
		//set core attrs
		foreach([ 'name' => $name, 'method' => $method, 'action' => $action, 'id' => $name . '-form' ] as $k => $v) {
			//attr already set?
			if(isset($opts[$k]) && $opts[$k]) {
				$opts['attr'][$k] = $opts[$k];
				unset($opts[$k]);
			} elseif(!isset($opts['attr'][$k]) || !$opts['attr'][$k]) {
				$opts['attr'][$k] = $v;
			}
		}
		//set name
		$opts['name'] = $name;
		//lowercase method
		$opts['attr']['method'] = strtolower($opts['attr']['method']);
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//set default message?
		if(!$this->message && $this->attr['method'] === 'post') {
			$this->message = 'Form successfully saved';
		}
	}

	public function __toString() {
		return $this->render();
	}

	public function attr($key, $val=null) {
		//is array?
		if(is_array($key)) {
			$this->attr = array_merge($this->attr, $key);
		} elseif($val !== null) {
			$this->attr[$key] = $val;
		}
		//chain it
		return $this;
	}

	public function input($name, array $config=[]) {
		//add field
		$this->fields[$name] = $config;
		//chain it
		return $this;
	}

	public function text($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'text' ], $config));
	}

	public function textarea($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'textarea' ], $config));
	}

	public function hidden($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'hidden', 'label' => '' ], $config));
	}

	public function password($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'password' ], $config));
	}

	public function file($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'file' ], $config));
	}

	public function select($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'select' ], $config));
	}

	public function checkbox($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'checkbox' ], $config));
	}

	public function radio($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'radio' ], $config));
	}

	public function button($name, array $config=[]) {
		return $this->input($name, array_merge([ 'type' => 'button' ], $config));
	}

	public function captcha($label, array $config=[]) {
		return $this->input('captcha', array_merge([ 'type' => 'captcha', 'label' => $label, 'validate' => 'captcha' ], $config));
	}

	public function submit($value, array $config=[]) {
		return $this->input('submit', array_merge([ 'type' => 'submit', 'value' => $value, 'label' => '' ], $config));
	}

	public function html($html, array $config=[]) {
		$name = mt_rand(10000, 100000);
		return $this->input($name, array_merge([ 'label' => '', 'html' => $html ], $config));
	}

	public function isValid() {
		//already run?
		if($this->isValid !== null) {
			return $this->isValid;
		}
		//form data
		$fields = [];
		$values = [];
		$method = $this->attr['method'] === 'get' ? 'get' : 'post';
		$global = $GLOBALS['_' . strtoupper($method)];
		$formId = $this->input->$method('id');
		//model data
		$modelId = $this->getModelId();
		$modelMeta = $this->getModelMeta();
		//method matched?
		if($method !== strtolower($_SERVER['REQUEST_METHOD'])) {
			return null;
		}
		//ID matched?
		if($modelId && $modelId != $formId) {
			return null;
		}
		//check fields match input
		foreach($this->fields as $name => $opts) {
			//is submit field?
			if(isset($opts['type']) && $opts['type'] === 'submit') {
				continue;
			}
			//does value exist?
			if(!isset($global[$name]) && !is_int($name)) {
				return null;
			}
			//add field
			$fields[$name] = $opts;
		}
		//reset errors
		$this->errors = [];
		$this->input->getValidator()->reset();
		//loop through fields
		foreach($fields as $name => $opts) {
			//is submit field?
			if(isset($opts['type']) && $opts['type'] === 'submit') {
				continue;
			}
			//set vars
			$filter = [];
			$validate = [];
			$override = isset($opts['override']) && $opts['override'];
			//setup validators
			foreach([ 'filter', 'validate' ] as $k) {
				//add model rules?
				if(!$override && isset($modelMeta[$name]) && $modelMeta[$name][$k]) {
					$tmp = is_array($modelMeta[$name][$k]) ? $modelMeta[$name][$k] : explode('|', $modelMeta[$name][$k]);
					$$k = array_merge($$k, $tmp);
				}
				//add form rules?
				if(isset($opts[$k]) && $opts[$k]) {
					$tmp = is_array($opts[$k]) ? $opts[$k] : explode('|', $opts[$k]);
					$$k = array_merge($$k, $tmp);
				}
			}
			//get value
			$values[$name] = $this->input->$method($name, [
				'field' => $name,
				'label' => isset($opts['label']) ? $opts['label'] : '',
				'filter' => $filter,
				'validate' => $validate,
			]);
		}
		//set vars
		$res = '';
		$id = $this->getModelId();
		//cache values
		$this->values = $values;
		$this->errors = $this->input->getValidator()->getErrors();
		$this->isValid = empty($this->errors);
		//save model?
		if($this->isValid && $this->model && $this->autosave) {
			//get result
			$id = $this->saveModelData($this->values, $this->errors);
			//save failed?
			if($this->errors || $id === false) {
				$res = false;
				$this->isValid = false;
			}
		}
		//successful submit?
		if($this->isValid) {
			//success callback?
			if($cb = $this->onSuccess) {
				//execute callback
				$res = $cb($this->values, $this->errors, $this->message);
				//still valid?
				if($res === false || $this->errors) {
					$this->isValid = false;
				}
			}
		} else {
			//error callback?
			if($cb = $this->onError) {
				//execute callback
				$res = $cb($this->errors);
				//update errors?
				if(is_array($res)) {
					$this->errors = $res;
				}
			}
		}
		//redirect user?
		if($this->isValid && $res && is_string($res)) {
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

	public function errors($field=null) {
		//one field?
		if($field) {
			return isset($this->errors[$field]) ? $this->errors[$field] : null;
		}
		//all fields
		return $this->errors;
	}

	public function values($field=null) {
		//one field?
		if($field) {
			return isset($this->values[$field]) ? $this->values[$field] : null;
		}
		//all fields
		return $this->values;
	}

	public function init() {
		//validate
		$this->isValid();
		//chain it
		return $this;
	}

	public function render() {
		//init
		$this->init();
		//set vars
		$html = '';
		$formAttr = [];
		$modelData = $this->getModelData();
		//loop through form attributes
		foreach($this->attr as $k => $v) {
			if(strlen($v) > 0) {
				$formAttr[] = $k . '="' . $v . '"';
			}
		}
		//open form
		$html .= '<form ' . implode(' ', $formAttr) . '>' . "\n";
		//success message?
		if($this->message) {
			if($this->isValid || (is_null($this->isValid) && $this->input->get('success') == 'true')) {
				$html .= '<div class="notice info">' . $this->message . '</div>' . "\n";
			}
		}
		//error summary?
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
				$html .= '<div class="error">' . $v . '</div>' . "\n";
			}
		}
		//add ID field?
		if($modelData && isset($modelData['id']) && $modelData['id']) {
			$html .= '<input type="hidden" name="id" value="' . $modelData['id'] . '">' . "\n";
		}
		//create fields
		foreach($this->fields as $name => $opts) {
			//set vars
			$field = '';
			//format opts
			$opts = array_merge([
				'name' => $name,
				'type' => 'text',
				'value' => null,
				'placeholder' => '',
				'label' => str_replace('_', ' ', ucfirst($name)),
				'error' => isset($this->errors[$name]) ? $this->errors[$name] : [],
			], $opts);
			//update value?
			if(isset($this->values[$name])) {
				//use input
				$opts['value'] = $this->values[$name];
			} elseif($modelData && $opts['value'] === null) {
				//use data source
				$tmp = isset($modelData[$name]) ? $modelData[$name] : '';
				//set value?
				if($tmp || $tmp === 0 || $tmp === '0') {
					$opts['value'] = (string) $tmp;
				}
			}
			//add label?
			if(!empty($opts['label'])) {
				$field .= '<label for="' . $opts['name'] . '">' . $opts['label'] . '</label>' . "\n";
			}
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
				$html .= '<div class="error">' . $error . '</div>' . "\n";
			}
			//close field wrapper
			if($opts['type'] !== 'hidden') {
				$html .= '</div>' . "\n";
			}
		}
		//close form
		$html .= '</form>';
		//go to form?
		if($this->errors && $this->attr['method'] === 'post') {
			$html .= "<script>document.getElementById('" . $this->name . "-form').scrollIntoView();</script>";
		}
		//return
		return $html;
	}

	protected function formatAttr(array $attr) {
		//attrs to remove
		$remove = [ 'name', 'value', 'label', 'error', 'validate', 'filter', 'before', 'after', 'override' ];
		//loop through attributes
		foreach($attr as $key => $val) {
			if(in_array($key, $remove)) {
				unset($attr[$key]);
			}
		}
		//return
		return $attr;
	}

	protected function getModelId() {
		//is object?
		if(is_object($this->model)) {
			return $this->model->id;
		}
		//return in array
		return isset($this->model['id']) ? $this->model['id'] : '';
	}

	protected function getModelMeta() {
		//set vars
		$modelMeta = [];
		//has mapper?
		if($this->orm && $this->orm->has($this->model)) {
			$modelMeta = $this->orm->mapper($this->model)->fields();
		}
		//return
		return $modelMeta;
	}

	protected function getModelData() {
		//set vars
		$modelData = $this->model;
		//has mapper?
		if($this->orm && $this->orm->has($this->model)) {
			$modelData = (array) $this->orm->mapper($this->model)->data();
		}
		//return
		return $modelData;
	}

	protected function saveModelData($values, &$errors) {
		//set vars
		$id = null;
		//save callback?
		if($cb = $this->onSave) {
			//execute callback
			$values = $cb($values, $errors);
			//update values?
			if(!is_array($values)) {
				throw new \Exception("onSave callback must return an array");
			}
		}
		//has mapper?
		if($values && $this->orm && $this->orm->has($this->model)) {
			//get mapper
			$mapper = $this->orm->mapper($this->model);
			//attempt save
			$id = $mapper->inject($values)->save();
			//get errors
			$errors = $mapper->errors();
		}
		//return
		return $id;
	}

}