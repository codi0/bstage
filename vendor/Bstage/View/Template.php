<?php

namespace Bstage\View;

class Template implements \ArrayAccess {

	protected $theme = '';
	protected $paths = [];

	protected $data = [];
	protected $dataClass = 'Bstage\Container\Config';

	protected $blocks = [];
	protected $extend = [];
	protected $callbacks = [];

	protected $curBlock = '';
	protected $curTemplate = '';

	protected $csrf;
	protected $events;
	protected $escaper;
	protected $shortcodes;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//escaper set?
		if(!$this->escaper) {
			$this->escaper = new \Bstage\Security\Escaper;
		}
		//check paths
		foreach($this->paths as $k => $v) {
			unset($this->paths[$k]);
			$this->addPath($v);
		}
		//wrap data?
		if(!is_object($this->data)) {
			$this->data = new $this->dataClass($this->data);
		}
		//set default site name?
		if(!$this->data->get('page.site')) {
			//set vars
			$siteName = '';
			//loop through domain parts
			foreach(explode('.', $_SERVER['HTTP_HOST']) as $part) {
				if(strlen($part) > strlen($siteName)) {
					$siteName = ucfirst($part);
				}
			}
			//set name?
			if(!empty($siteName)) {
				$this->data->set('page.site', $siteName);
			}
		}
	}

	public function __call($action, array $params=[]) {
		//set vars
		$cb = null;
		//get callback
		if(isset($this->callbacks[$action])) {
			$cb = $this->callbacks[$action];
		} elseif(method_exists($this, '_' . $action)) {
			$cb = [ $this, '_' . $action ];
		} elseif(is_callable($action)) {
			$cb = $action;
		}
		//valid callback?
		if($cb === null) {
			throw new \Exception('Invalid callback ' . $action);
		}
		//execute callback
		return call_user_func_array($cb, $params);	
	}

	public function addPath($dir, $prepend=false) {
		//is rendering?
		if($this->curTemplate) {
			throw new \Exception('Rendering in progress');
		}
		//format dir
		$dir = rtrim($dir, '/');
		//valid dir?
		if(!$dir || !is_dir($dir)) {
			return false;
		}
		//already set?
		if(in_array($dir, $this->paths)) {
			return true;
		}
		//set property
		if($prepend) {
			array_unshift($this->paths, $dir);
		} else {
			$this->paths[] = $dir;
		}
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

	public function extend($name, $required=true) {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//set property
		$this->extend[$this->curTemplate] = [
			'name' => $name,
			'required' => $required,
		];
	}

	public function block($name, $default='') {
		//is rendering?
		if(!$this->curTemplate) {
			throw new \Exception('Rendering not started');
		}
		//block found?
		if(isset($this->blocks[$name])) {
			$output = $this->blocks[$name];
		} else {
			$output = $default;
		}
		//is title?
		if($name === 'title') {
			//get site name
			$siteName = $this->data->get('page.site');
			//add to output?
			if($siteName && strpos($output, $siteName) === false) {
				$output = $siteName . ($output ? ': ' . $output : '');
			}
		}
		//display
		return $output;
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
				$this->blocks[$this->curBlock] = $output ? $output . "\n" : "";
			}
			//reset flag
			$this->curBlock = '';
		}
	}

	public function render($template, array $data=[], $asStr=false) {
		//is rendering?
		if($this->curTemplate) {
			return $this->loadTemplate($template, $data, [
				'child' => true,
			]);
		}
		//template event?
		if($this->events) {
			//EVENT: template.theme
			$event = $this->events->dispatch('template.theme', [
				'theme' => $this->theme,
				'template' => $template,
				'data' => $data,
			]);
			//update theme
			$this->theme = $event->theme;
			$template = $event->template;
			$data = $event->data;
		}
		//start buffer
		ob_start();
		//load template
		$this->loadTemplate($template, $data);
		//get output
		$output = trim(ob_get_clean());
		//parse output?
		if(strpos($output, '{') !== false) {
			$output = $this->parseTemplate($output);
		}
		//replace shortcodes?
		if($this->shortcodes) {
			$output = $this->shortcodes->injectHtml($output);
		}
		//csrf protection?
		if($this->csrf) {
			$output = $this->csrf->injectHtml($output);
		}
		//template event?
		if($this->events) {
			//EVENT: template.head and template.footer
			foreach([ 'head' => '</head>', 'footer' => '</body>' ] as $k => $v) {
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
			$event = $this->events->dispatch('template.output', [
				'output' => $output,
			]);
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

	/* INTERNAL: HELPER METHODS */

	protected function loadTemplate($name, array $data=[], array $opts=[]) {
		//set vars
		$path = '';
		$themes = [ $this->theme, '' ];
		$name = str_replace([ ' ', '/' ], '-', $name);
		$opts = array_merge([ 'child' => false, 'required' => true ], $opts);
		//merge data
		$this->data->merge($data);
		//loop through paths
		foreach($this->paths as $p) {
			//loop through themes
			foreach($themes as $t) {
				//path to test
				$test = $p . ($t ? '/' . $t : '') . '/' . $name . '.php';
				//file exists?
				if(is_file($test)) {
					$path = $test;
					break 2;
				}
			}
		}
		//path found?
		if(empty($path)) {
			if($opts['required']) {
				throw new \Exception("Template not found: " . $name);
			} else {
				return;
			}
		}
		//set flag
		$this->curTemplate = $path;
		//add buffer
		ob_start();
		//load file
		(function($tpl, $__path) {
			include($__path);
		})($this, $path);
		//end section?
		if($this->curBlock) {
			$this->stop();
		}
		//buffer returned?
		if($output = trim(ob_get_clean())) {
			//is html layout?
			if(stripos($output, '<html') !== false) {
				echo $output;
			} else {
				//optional layout?
				if(!$opts['child']) {
					$this->extend('layout', false);
				}
				//add to main
				$this->start('main');
				echo $output;
				$this->stop();
			}
		}
		//extend template?
		if(isset($this->extend[$path])) {
			//cache parent name
			$tmp = $this->extend[$path];
			//remove reference
			unset($this->extend[$path]);
			//load parent
			$this->loadTemplate($tmp['name'], [], [
				'child' => true,
				'required' => $tmp['required'],
			]);
		}
	}

	protected function parseTemplate($output) {
		//cache object
		$tpl = $this;
		//replace static tags
		$output = str_replace('{else}', '<?php } else { ?>', $output);
		$output = str_replace('{/section}', '<?php $tpl->stop(); ?>', $output);
		$output = str_replace([ '{/if}', '{/foreach}' ], '<?php } ?>', $output);
		//replace {block}, {extend}, {section}
		$output = preg_replace_callback('/{(block|extend|section):([^}]+)}/i', function($match) use($tpl) {
			//get method
			$method = $match[1];
			$name = trim($match[2]);
			//is section?
			if($method === 'section') {
				$method = 'start';
			}
			//call method
			return $tpl->$method($name);
		}, $output);
		//replace {if}
		$output = preg_replace_callback('/{if ([^}]+)}/i', function($match) use($tpl) {
			//set vars
			$neg = '';
			$expr = trim($match[1], '{} ');
			//negative if?
			if($expr[0] === '!') {
				$neg = '!';
				$expr = trim(substr($match[1], 1));
			}
			//use raw variable?
			if($expr[0] === '$') {
				$expr = str_replace('.', '->', $expr);
			} else {
				$expr = '$tpl["' . $expr . '"]';
			}
			//replace with php
			return '<?php if(' . $neg . $expr . ') { ?>';
		}, $output);
		//replace {foreach}
		$output = preg_replace_callback('/{foreach ([^\s]+) as ([^}]+)}/i', function($match) use($tpl) {
			//replace with php
			return '<?php foreach($tpl["' . $match[1] . '"] as ' . $match[2] . ') { ?>';
		}, $output);
		//replace {{variables}}
		$output = preg_replace_callback('/{{([^}]+)}}/i', function($match) use($tpl) {
			//set vars
			$actions = '';
			$expr = trim($match[1], '{} ');
			//use raw variable?
			if($expr && $expr[0] === '$') {
			//split string
				$exp = explode('|', $expr);
				//has actions?
				if(count($exp) > 1) {
					$expr = array_shift($exp);
					$actions = implode('|', $exp);
				}
				//replace with php
				return '<?php echo $tpl->execExpr(' . str_replace('.', '->', $expr) . ', "' . $actions . '"); ?>';
			} else {
				//replace with value
				return $tpl->parseExpr($expr);
			}
		}, $output);
		//php code injected?
		if(strpos($output, '<?php') !== false) {
			//eval output
			$output = (function($output, $tpl) {
				ob_start();
				eval(' ?>' . $output . '<?php ');
				return ob_get_clean();
			})($output, $tpl);
		}
		//return
		return $output;	
	}

	protected function parseExpr($key) {;
		//set vars
		$value = null;
		$actions = array_map('trim', explode('|', $key));
		//is function call?
		if(!preg_match('/^(\w+)\(([^\)]+)?\)$/', $actions[0])) {
			//check data store
			$key = array_shift($actions);
			$value = $this->data->get($key, '');
		}
		//execute value
		return $this->execExpr($value, $actions);
	}

	protected function execExpr($value, $actions=[]) {
		//set vars
		$escaped = false;
		//actions to array?
		if(is_string($actions)) {
			$actions = $actions ? explode('|', $actions) : [];
		}
		//loop through actions
		foreach($actions as $action) {
			//set vars
			$cb = null;
			$params = [];
			$action = trim($action);
			//escape rule?
			if(strpos($action, 'esc') === 0) {
				//use escaper
				$escaped = true;
				$rule = lcfirst(substr($action, 3));
				$value = $this->escaper->escape($value, $rule);
			} else {
				//function call?
				if(preg_match('/^(\w+)\(([^\)]+)?\)$/', $action, $match)) {
					//update action
					$params = [];
					$action = $match[1];
					//get params
					if(isset($match[2]) && preg_match_all("/'[^']*'|[^,]+/", $match[2], $m)) {
						if($m) {
							$params = array_map(function($i) {
								return trim(trim($i), "'");
							}, array_shift($m));
						}
					}
				}
				//add to params?
				if($value !== null) {
					array_unshift($params, $value);
				}
				//execute callback?
				if($action === 'raw') {
					$escaped = true;
				} else {
					$value = $this->__call($action, $params);
				}
				
			}
		}
		//auto escape string?
		if(!$escaped && is_string($value)) {
			$value = $this->escaper->escape($value, 'html');
		}
		//return
		return $value;
	}

	/* INTERNAL: DEFAULT CALLBACKS */

	protected function _ifEmpty($value, $default) {
		return $value ?: $default;
	}

	protected function _ifNotEmpty($value, $append) {
		return $value ? $value . $append : $value;
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
		//return
		return $this->parseExpr($key);
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

}