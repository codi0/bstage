<?php

namespace Bstage\View\Shortcode;

class ShortcodeManager {

	protected $app;
	protected $store = [];
	protected $classFormat = '{vendor}\\View\\Shortcode\\{name}';

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function get($name, array $params=[], $exception=true) {
		//set vars
		$callback = null;
		//cached callback?
		if(isset($this->store[$name])) {
			$callback = $this->store[$name];
		} elseif($class = $this->app->class($this->classFormat, $name)) {
			$callback = [ new $class([ 'app' => $this->app ]), 'parse' ];
		}
		//callback found?
		if(!$callback) {
			//throw exception?
			if($exception) {
				throw new \Exception("Shortcode class not found: $name");
			} else {
				return '';
			}
		}
		//execute callback
		return call_user_func($callback, $params, $this->app);
	}

	public function add($name, $callback) {
		//add to cache
		$this->store[$name] = $callback;
		//return
		return true;
	}

	public function injectHtml($output) {
		//set vars
		$sm = $this;
		//parse all shortcodes
		return preg_replace_callback('/\[([^\]]+)\]/', function($matches) use($sm) {
			//get shortcode parts
			$parts = array_map('trim', explode(' ', $matches[1], 2));
			$name = $parts[0];
			$attrs = isset($parts[1]) ? array_map('trim', explode(' ', $parts[1])) : [];
			//loop through attributes
			foreach($attrs as $key => $val) {
				//delete key
				unset($attrs[$key]);
				//set attribute?
				if(strpos($val, '=') !== false) {
					//get attr key and value
					list($k, $v) = array_map('trim', explode('=', $val, 2));
					//remove quotes
					$attrs[$k] = trim($v, '"');
				}
			}
			//get output
			return $sm->get($name, $attrs, false);
		}, $output);
	}

}