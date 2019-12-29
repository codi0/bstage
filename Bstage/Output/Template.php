<?php

namespace Bstage\Output;

class Template implements \ArrayAccess {

	protected $dir = '.';
	protected $theme = '';

	protected $data = array();
	protected $dataClass = 'Bstage\Container\Config';

	protected $blocks = array();
	protected $extend = array();
	protected $callbacks = array();

	protected $curBlock = '';
	protected $curTemplate = '';

	protected $events = null;

	/* INTERNAL: MAGIC METHODS */

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//wrap data?
		if(!is_object($this->data)) {
			$this->data = new $this->dataClass($this->data);
		}
		//set default site name?
		if(!$this->data->get('page.site')) {
			//guess site name
			$this->data->set('page.site', (function() {
				//set vars
				$res = '';
				//loop through domain parts
				foreach(explode('.', $_SERVER['HTTP_HOST']) as $part) {
					if(strlen($part) > strlen($res)) {
						$res = ucfirst($part);
					}
				}
				//return
				return $res;
			})());
		}
	}

	public function __call($action, array $params=array()) {
		//set vars
		$cb = null;
		//get callback
		if(isset($this->callbacks[$action])) {
			$cb = $this->callbacks[$action];
		} elseif(method_exists($this, '_' . $action)) {
			$cb = array( $this, '_' . $action );
		} elseif(function_exists($action)) {
			$cb = $action;
		}
		//valid callback?
		if(!$cb || !is_callable($cb)) {
			throw new \Exception('Invalid callback ' . $action);
		}
		//execute callback
		return call_user_func_array($cb, $params);	
	}

	/* PUBLIC: SETUP METHODS */

	public function setDir($dir) {
		//is rendering?
		if($this->curTemplate) {
			throw new \Exception('Rendering in progress');
		}
		//set property
		$this->dir = $dir;
		//return
		return true;
	}

	public function setTheme($name) {
		//is rendering?
		if($this->curTemplate) {
			throw new \Exception('Rendering in progress');
		}
		//set property
		$this->theme = $name;
		//return
		return true;
	}

	public function setData($key, $val) {
		//is rendering?
		if($this->curTemplate) {
			throw new \Exception('Rendering in progress');
		}
		//set data
		return $this->data->set($key, $val);
	}

	public function setCallback($key, $val) {
		//is rendering?
		if($this->curTemplate) {
			throw new \Exception('Rendering in progress');
		}
		//add callback
		$this->callbacks[$key] = $val;
		//return
		return true;
	}

	/* PUBLIC: TEMPLATE METHODS */

	public function extend($name) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//set property
		$this->extend[$this->curTemplate] = $name;
	}

	public function block($name, $default='') {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//block found?
		if(isset($this->blocks[$name])) {
			echo $this->blocks[$name];
		} else {
			echo $default;
		}
	}

	public function start($name) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//set block
		$this->curBlock = $name;
		//start buffer
		ob_start();
	}

	public function stop() {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//block defined?
		if($this->curBlock) {
			//get output
			$output = trim(ob_get_clean());
			//store block output?
			if(!isset($this->blocks[$this->curBlock])) {
				$this->blocks[$this->curBlock] = $output . "\n";
			}
			//reset flag
			$this->curBlock = '';
		}
	}

	public function render($template, array $data=array(), $asStr=false) {
		//is rendering?
		if($this->curTemplate) {
			return $this->loadTemplate($template, $data);
		}
		//template event?
		if($this->events) {
			//EVENT: template.select
			$event = $this->events->dispatch('template.select', array(
				'template' => $template,
				'theme' => $this->theme,
				'dir' => $this->dir,
				'data' => $data,
			));
			//update inputs
			$template = $event->template ?: $template;
			$this->theme = $event->theme ?: $this->theme;
			$this->dir = $event->dir ?: $this->dir;
			$data = $event->data ?: $data;
		}
		//start buffer
		ob_start();
		//load template
		$this->loadTemplate($template, $data);
		//get output
		$output = trim(ob_get_clean());
		//template event?
		if($this->events) {
			//EVENT: template.head and template.footer
			foreach(array( 'head' => '</head>', 'footer' => '</body>' ) as $k => $v) {
				//start buffer
				ob_start();
				//trigger event
				$this->events->dispatch('template.' . $k);
				//replace output?
				if($tmp = trim(ob_get_clean())) {
					$output = str_ireplace($v, $tmp . "\n" . $v, $output);
				}
			}
			//EVENT: template.output
			$event = $this->events->dispatch('template.output', array(
				'output' => $output,
			));
			//update output
			$output = $event->output;
		}
		//reset flag
		$this->curTemplate = '';
		//return?
		if($asStr) {
			return $output;
		}
		//echo
		echo $output;
	}

	/* INTERNAL: ARRAY ACCESS METHODS */

	public function offsetExists($key) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//check data exists
		return $this->data->get($key) !== null;
	}

    public function offsetGet($key) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//set vars
		$escaped = false;
		$actions = explode('|', $key);
		//key is function?
		if(strpos($actions[0], '(') !== false) {
			$value = null;
		} else {
			$key = array_shift($actions);
			$value = $this->data->get($key, '');
		}
		//loop through actions
		foreach($actions as $action) {
			//set vars
			$cb = null;
			$params = array();
			//get params
			if(preg_match('/^(.*)\((.*)\)$/', $action, $match)) {
				$action = $match[1];
				$params = explode(',', $match[2]);
			}
			//add to params?
			if($value !== null) {
				array_unshift($params, $value);
			}
			//execute callback
			$value = $this->__call($action, $params);
			//escape action called?
			if($action === 'raw' || strpos($action, 'esc') === 0) {
				$escaped = true;
			}
		}
		//auto escape?
		if(!$escaped && is_scalar($value)) {
			$value = $this->_escHtml($value);
		}
		//return
		return $value;
	}

	public function offsetSet($key, $val) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//set data
		return $this->data->set($key, $val);
	}

	public function offsetUnset($key) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//delete data
		return $this->data->delete($key);
	}

	/* INTERNAL: HELPER METHODS */

	protected function loadTemplate($template, array $data=array()) {
		//merge data
		$this->data->merge($data);
		//paths to try
		$paths = array(
			0 => $template,
			1 => $this->dir . '/' . $this->theme . '/' . $template . '.php',
			2 => $this->dir . '/' . $template . '.php',
		);
		//reset template
		$template = null;
		//check for path match
		foreach($paths as $index => $path) {
			//valid path format?
			if($path[0] !== '/' || strpos($path, '//') > 0) {
				continue;
			}
			//file found?
			if(is_file($path)) {
				$template = $path;
				break;
			}
		}
		//template matched?
		if($template === null) {
			throw new \Exception("Template not found: " . $paths[0]);
		}
		//set flag
		$this->curTemplate = $template;
		//generate output
		(function($__path, $tpl) {
			//load file
			include($__path);
		})($template, $this);
		//extend template?
		if(isset($this->extend[$template])) {
			//cache parent name
			$tmp = $this->extend[$template];
			//remove reference
			unset($this->extend[$template]);
			//load parent
			$this->loadTemplate($tmp);
		}
	}

	/* INTERNAL: DEFAULT CALLBACKS */

	protected function _escHtml($value) {
		return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
	}

	protected function _escAttr($value) {
		return preg_replace_callback('/[^a-z0-9,\.\-_]/iSu', function($matches) {
			$chr = $matches[0];
			$ord = ord($chr);
			if(($ord <= 0x1f && $chr != "\t" && $chr != "\n" && $chr != "\r") || ($ord >= 0x7f && $ord <= 0x9f)) {
				return '&#xFFFD;'; //replacement for undefined characters in html
			}
			$ord = hexdec(bin2hex($chr));
			if($ord > 255) {
				return sprintf('&#x%04X;', $ord);
			} else {
				return sprintf('&#x%02X;', $ord);
			}
		}, $value);
	}

	protected function _escJs($value) {
		return preg_replace_callback('/[^a-z0-9,\._]/iSu', function($matches) {
			$chr = $matches[0];
			if(strlen($chr) == 1) {
				return sprintf('\\x%02X', ord($chr));
			}
			$hex = strtoupper(bin2hex($chr));
			if(strlen($hex) <= 4) {
				return sprintf('\\u%04s', $hex);
			} else {
				return sprintf('\\u%04s\\u%04s', substr($hex, 0, 4), substr($hex, 4, 4));
			}
		}, $value);
	}

	protected function _escCss($value) {
		return preg_replace_callback('/[^a-z0-9]/iSu', function($matches) {
			$chr = $matches[0];
			if(strlen($chr) == 1) {
				$ord = ord($chr);
			} else {
				$ord = hexdec(bin2hex($chr));
			}
			return sprintf('\\%X ', $ord);	
		}, $value);
	}

	protected function _escUrl($value) {
		if(strpos($value, '?') !== false) {
			$url = explode('?', $value, 2);
			parse_str($url[1], $arr);
			return $url[0] . ($arr ? '?' . http_build_query($arr, '', '&amp;', PHP_QUERY_RFC3986) : '');
		} else {
			return rawurlencode($value);
		}
	}

	protected function _raw($value) {
		return $value;
	}

	protected function _ifEmpty($value, $default) {
		return $value ?: $default;
	}

	protected function _ifNotEmpty($value, $append) {
		return $value ? $value . $append : $value;
	}

}