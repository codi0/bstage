<?php

namespace Bstage\View;

class Html {

	protected $captcha = null;
	protected $createUrl = null;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function input($name, $value='', array $opts=[]) {
		//set opts
		$opts = array_merge([
			'type' => 'text',
			'name' => ($name === 'submit') ? '' : $name,
			'value' => ($name === 'captcha') ? '' : $value,
		], $opts);
		//return
		return '<input' . $this->formatAttr($opts) . '>';
	}

	public function text($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'text' ], $opts));
	}

	public function hidden($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'hidden' ], $opts));
	}

	public function password($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'password' ], $opts));
	}

	public function file($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'file' ], $opts));
	}

	public function button($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'button' ], $opts));
	}

	public function submit($name, $value='', array $opts=[]) {
		return $this->input($name, $value, array_merge([ 'type' => 'submit' ], $opts));
	}

	public function textarea($name, $value='', array $opts=[]) {
		//set opts
		$opts = array_merge([
			'name' => $name,
		], $opts);
		//return
		return '<textarea' . $this->formatAttr($opts) . '>' . $value . '</textarea>';
	}

	public function checkbox($name, $value='', array $opts=[]) {
		//format attr
		$opts = array_merge([
			'type' => 'checkbox',
			'options' => [ $name ],
		], $opts);
		//set vars
		$html = '';
		//loop through options
		foreach($opts['options'] as $k => $v) {
			//is array?
			if(count($opts) > 1) {
				$n = $name . '[' . $k . ']';
				$checked = (isset($value[$k]) && $value[$k]) || $value === $k ? ' checked' : '';
			} else {
				$n = $name;
				$checked = ($value === $n || $value == 1) ? ' checked' : '';
			}
			//add html
			$html .= '<span>';
			$html .= '<input type="' . $opts['type'] . '" name="' . $n . '" value="1"' . $checked . '>';
			$html .= ucfirst($v);
			$html .= '</span>' . "\n";
		}
		//return
		return $html;
	}

	public function radio($name, $value='', array $opts=[]) {
		//set type as radio
		$opts['type'] = 'radio';
		//delegate to checkbox method
		return $this->checkbox($name, $value, $opts);
	}

	public function select($name, $value='', array $opts=[]) {
		//format attr
		$opts = array_merge([
			'name' => $name,
			'options' => [],
		], $opts);
		//open select
		$html = '<select' . $this->formatAttr($opts) . '>' . "\n";
		//loop through options
		foreach($opts['options'] as $key => $val) {
			//standard opt?
			if(!is_array($val)) {
				$html .= '<option value="' . $key . '"' . ($key == $value ? ' selected' : '') . '>' . $val . '</option>' . "\n";
				continue;
			}
			//open opt group
			$html .= '<optgroup label="' . $key . '">' . "\n";
			//loop through options
			foreach($val as $k => $v) {
				$html .= '<option value="' . $k . '"' . ($k == $value ? ' selected' : '') . '>' . $v . '</option>' . "\n";
			}
			//close opt group
			$html .= '</optgroup>' . "\n";
		}
		//close select
		$html .= '</select>';
		//return
		return $html;
	}

	public function nav($name, $value='', array $opts=[]) {
		//open nav
		$html = '<nav id="' . $name . '">' . "\n";
		//add toggle
		$html .= '<div class="toggle">' . "\n";
		$html .= '<i onclick="document.getElementById(\'' . $name . '\').classList.toggle(\'open\');"></i>' . "\n";
		$html .= '</div>' . "\n";
		//open menu
		$html .= '<div class="menu">' . "\n";
		//loop through opts
		foreach($opts as $k => $v) {
			//set vars
			$classes = [];
			$exact = true;
			//wildcard match?
			if(substr($k, -1) === '*') {
				$exact = false;
				$k = substr($k, 0, -1);
			}
			//is active?
			if(($exact && $k == $value) || (!$exact && strpos($value, $k) === 0)) {
				$classes[] = 'active';
			}
			//create attribute
			$classes = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
			//add link
			$html .= '<a href="' . $this->createUrl($k) . '"' . $classes . '>' . $v . '</a>' . "\n";
		}
		//close menu
		$html .= '</div>' . "\n";
		//close nav
		$html .= '</nav>' . "\n";
		//return
		return $html;
	}

	public function captcha($name, $value='', array $opts=[]) {
		//set opts
		$opts = array_merge([
			'format' => 'jpeg',
			'raw' => true,
		]);
		//captcha set?
		if(!$this->captcha) {
			throw new \Exception('Captcha object not set');
		}
		//image data
		$imgData = $this->captcha->render($opts);
		//create html
		$html  = $this->input($name, $value, [ 'autocomplete' => 'off' ]) . "\n";
		$html .= '<div class="captcha-image">' . "\n";
		$html .= '<img src="data:image/' . $opts['format'] . ';base64,' . base64_encode($imgData) . '">' . "\n";
		$html .= '</div>';
		//return
		return $html;
	}

	protected function formatAttr(array $opts) {
		//set vars
		$html = '';
		//loop through attr
		foreach($opts as $k => $v) {
			if($k && $v && is_scalar($v)) {
				$html .= ' ' . $k . '="' . $v . '"';
			}
		}
		//return
		return $html;
	}

	protected function createUrl($path) {
		//create url?
		if($this->createUrl) {
			$func = $this->createUrl;
			$path = $func($path) ?: $path;
		}
		//return
		return $path;
	}

}