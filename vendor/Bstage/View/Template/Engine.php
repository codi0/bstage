<?php

namespace Bstage\View\Template;

class Engine {

	protected $paths = [];
	protected $theme = '';
	protected $layout = 'base';
	protected $ext = 'php';

	protected $blocks = [];
	protected $extend = [];
	protected $curBlock = [];
	protected $curTemplate = '';

	protected $data = [];
	protected $dataClass = 'Bstage\Container\Config';

	protected $caller;
	protected $callbacks = [];

	protected $csrf;
	protected $escaper;
	protected $events;
	protected $html;
	protected $shortcodes;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//check paths
		foreach($this->paths as $k => $v) {
			unset($this->paths[$k]);
			$this->addPath($v);
		}
		//wrap data?
		if($this->dataClass) {
			$this->data = new $this->dataClass([ 'data' => $this->data ]);
		}
	}

	public function addPath($dir, $prepend=false) {
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
		//set property
		$this->theme = $name;
		//return
		return true;
	}

	public function setLayout($name) {
		//set property
		$this->layout = $name;
		//return
		return true;
	}

	public function getData($key, $default=null) {
		return $this->data->get($key, $default);
	}

	public function setData($key, $val) {
		return $this->data->set($key, $val);
	}

	public function setCallback($key, $val) {
		//add callback
		$this->callbacks[$key] = $val;
		//return
		return true;
	}

	public function call($method, array $args=[]) {
		//set vars
		$cb = null;
		$iMethod = '_' . $method;
		//core method?
		if(method_exists($this, $iMethod)) {
			return $this->$iMethod(...$args);
		}
		//test for callback
		$cb = isset($this->callbacks[$method]) ? $this->callbacks[$method] : $method;
		//valid callback?
		if(!is_callable($cb)) {
			throw new \Exception("Invalid callback: $method");
		}
		//execute callback
		return call_user_func_array($cb, $args);	
	}

	public function render($template, array $data=[], $asStr=false) {
		//paths set?
		if(!$this->paths) {
			throw new \Exception("No template paths set");
		}
		//merge data?
		if(!empty($data)) {
			$this->data->merge($data);
		}
		//is 404?
		if($template == '404') {
			$this->data->set('meta.noindex', 1);
		}
		//template event?
		if($this->events) {
			//EVENT: template.select
			$event = $this->events->dispatch('template.select', [
				'theme' => $this->theme,
				'layout' => $this->layout,
				'template' => $template,
				'data' => $this->data,
			]);
			//get updated values
			$this->theme = $event->theme;
			$this->layout = $event->layout;
			$template = $event->template;
		}
		//add theme path?
		if($this->theme) {
			$this->addPath($this->paths[0] . '/' . $this->theme, true);
		}
		//create caller?
		if(!$this->caller) {
			$this->caller = new Caller([ 'engine' => $this ]);
		}
		//start buffer
		ob_start();
		//load template
		$this->loadTemplate($template);
		//get output
		$output = trim(ob_get_clean());
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
				'page' => $template,
			]);
			//update output
			$output = $event->output;
		}
		//reset vars
		$tpl = $this;
		$this->curTemplate = '';
		//replace shortcodes?
		if($this->shortcodes) {
			$output = $this->shortcodes->injectHtml($output);
		}
		//replace escaped variables
		$output = preg_replace_callback('/{{(.*)}}/U', function($parts) use($tpl) {
			return $tpl->caller->expr($parts[1], true);
		}, $output);
		//csrf protection?
		if($this->csrf) {
			$output = $this->csrf->injectHtml($output);
		}
		//return?
		if($asStr) {
			return $output;
		}
		//echo
		echo $output;
	}

	protected function loadTemplate($name, $required=true) {
		//set vars
		$file = '';
		//loop through paths
		foreach($this->paths as $path) {
			//build file path
			$filePath = $path . '/' . $name . '.' . $this->ext;
			//file exists?
			if(is_file($filePath)) {
				$file = $filePath;
				break;
			}
		}
		//template found?
		if(empty($file)) {
			if($required) {
				throw new \Exception("Template not found: " . $name);
			} else {
				return;
			}
		}
		//set flag
		$this->curTemplate = $file;
		//add buffer
		ob_start();
		//load file
		(function($tpl, $__file) {
			include($__file);
		})($this->caller, $file);
		//end section?
		if($this->curBlock) {
			$this->_stop();
		}
		//buffer returned?
		if($output = trim(ob_get_clean())) {
			//is html layout?
			if(stripos($output, '<html') !== false) {
				echo $output;
			} else {
				//set layout?
				if($this->layout && !isset($this->extend[$file])) {
					$this->_extend('layouts/' . $this->layout, false);
				}
				//add to main
				$this->_start('main');
				echo $output;
				$this->_stop();
			}
		}
		//extend template?
		if(isset($this->extend[$file])) {
			//cache parent name
			$tmp = $this->extend[$file];
			//remove reference
			unset($this->extend[$file]);
			//load parent
			$this->loadTemplate($tmp['name'], $tmp['required']);
		}
	}

	protected function _extend($name, $required=true) {
		//set property
		$this->extend[$this->curTemplate] = [
			'name' => $name,
			'required' => $required,
		];
	}

	protected function _block($name, $default='') {
		//block found?
		if(isset($this->blocks[$name])) {
			$output = $this->blocks[$name];
		} else {
			$output = $default;
		}
		//is title?
		if($name === 'title') {
			return $this->_title($output);
		}
		//display
		return $output;
	}

	protected function _start($name, $replace=false) {
		//set block
		$this->curBlock = [
			'name' => $name,
			'replace' => $replace,
		];
		//start buffer
		ob_start();
	}

	protected function _stop() {
		//block defined?
		if($this->curBlock) {
			//has output?
			if($output = trim(ob_get_clean())) {
				//set vars
				$name = $this->curBlock['name'];
				$replace = $this->curBlock['replace'];
				//create empty block?
				if(!isset($this->blocks[$name]) || $replace) {
					$this->blocks[$name] = '';
				}
				//add content
				$this->blocks[$name] = $output . "\n" . $this->blocks[$name];
			}
			//reset cache
			$this->curBlock = [];
		}
	}

	protected function _esc($value, $rules) {
		return $this->escaper->escape($value, $rules);
	}

	protected function _title($title=null) {
		//set vars
		$segs = [];
		//extract site name?
		if(!$siteName = $this->data->get('meta.name')) {
			//loop through domain parts
			foreach(explode('.', $_SERVER['HTTP_HOST']) as $part) {
				if(strlen($part) > strlen($siteName)) {
					$siteName = ucfirst($part);
				}
			}
			//set name
			$this->data->set('meta.name', $siteName);
		}
		//set title?
		if(!$title) {
			$title = $this->data->get('meta.title');
		}
		//add to output?
		if($title) {
			if($siteName && stripos($title, $siteName) === false) {
				$title = $siteName . ($title ? ': ' . $title : '');
			}
		} else {
			//has path?
			if(isset($_SERVER['PATH_INFO'])) {
				$segs = explode('/', str_replace([ '_', '-' ], ' ', trim($_SERVER['PATH_INFO'], '/')));
			}
			//auto generate title
			$title = $siteName . ($segs ? ': ' . ucfirst($segs[0]) : '');
		}
		//return
		return $this->_esc($title, 'html');	
	}

	protected function _asset($name, $tag=true) {
		//asset handler
		$loadAsset = function($ext, $url, $file=null) {
			if($ext === 'js') {
				if($url) {
					return '<script defer src="' . $url . '"></script>' . "\n";
				}
				if($file) {
					$output  = '<script>' . "\n";
					$output .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
					$output .= file_get_contents($file) . "\n";
					$output .= '})' . "\n";
					$output .= '</script>' . "\n";
					return $output;
				}
			}
			if($ext === 'css') {
				if($url) {
					return '<link rel="stylesheet" href="' . $url . '">' . "\n";
				}
				if($file) {
					$output  = '<style>' . "\n";
					$output .= file_get_contents($file) . "\n";
					$output .= '</style>' . "\n";
					return $output;
				}
			}
			return null;
		};
		//get file extension
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		//is absolute url?
		if(strpos($name, '//') !== false) {
			return $tag ? $loadAsset($ext, $name) : $name;
		}
 		//loop through file paths
		foreach($this->paths as $path) {
			//set vars
			$url = '';
			$file = $path . '/' . $name;
			//file exists?
			if(is_file($file)) {
				//build url?
				if($tmp = $this->call('url', [ $file ])) {
					$time = filemtime($file);
					$url = $tmp . ($time ? '?' . $time : '');
					$res = $url;
				}
				//load tag?
				if($tag && in_array($ext, [ 'js', 'css' ])) {
					return $loadAsset($ext, $url, $file);
				}
				//return url?
				if($url) {
					return $url;
				}
			}
		}
		//not found
		return null;
	}

	protected function _html($tag, $val) {
		$args = func_get_args();
		$tag = array_shift($args);
		return $this->html->$tag(...$args);	
	}

	protected function _paragraphs($value) {
		//set vars
		$paras = [];
		$value = preg_split("/\n+/", str_replace("\r\n", "\n", $value));
		//loop through paragraphs
		foreach($value as $v) {
			$paras[] = '<p>' . $this->_esc($v, 'html') . '</p>';
		}
		//return
		return implode("\n", $paras);
	}

	protected function _ellipsis($value, $length) {
		//clean up spacing
		$value = preg_replace('/\s+/', ' ', strip_tags($value));
		//limit length?
		if(strlen($value) > $length) {
			$value = substr($value, 0, $length) . '...';
		}
		//return
		return $value;
	}

	protected function _relativeTime($value) {
		//to timestamp?
		if(!is_numeric($value)) {
			$value = strtotime($value);
		}
		//relative time
		$diff = time() - $value;
		//just now?
		if($diff < 60) {
			return 'Just now';
		}
		//use minutes?
		if($diff < 3600) {
			return ceil($diff / 60) . ' mins ago';
		}
		//use hours?
		if($diff < 86400) {
			return ceil($diff / 3600) . ' hours ago';
		}
		//use yesterday?
		if($diff < (86400 * 2)) {
			return 'Yesterday';
		}
		//use days?
		if($diff < (86400 * 7)) {
			return ceil($diff / 86400) . ' days ago';
		}
		//use date
		return date('j M Y', $value);
	}

}