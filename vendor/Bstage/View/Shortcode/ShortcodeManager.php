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
				return null;
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
		return preg_replace_callback('/\[([a-z0-9\-\_]+)(\s[^\]]+)?\]/i', function($parts) use($sm) {
			//set vars
			$attr = [];
			$key = null;
			$name = $parts[1];
			//skip css attr?
			if(in_array($name, [ 'readonly', 'disabled', 'hidden' ])) {
				return $parts[0];
			}
			//has attributes?
			if(isset($parts[2]) && $parts[2]) {
				//split string
				if(preg_match_all('/("[^"]*")|[^"]*/', $parts[2], $matches)) {
					//loop through matches
					foreach($matches[0] as $m) {
						//key?
						if(strpos($m, '=') !== false) {
							$key = trim($m, '= ');
							continue;
						}
						//value?
						if($key && $m) {
							$attr[$key] = trim($m, '" ');
							$key = null;
						}
					}
				}
			}
			//get output
			$output = $sm->get($name, $attr, false);
			//return
			return ($output !== null) ? $output : $parts[0];
		}, $output);
	}

}