<?php

namespace Bstage\App;

class Kernel {

	protected $meta = [];

	public function __construct(array $opts=[]) {
		//library path
		$libPath = dirname(dirname(__DIR__));
		//set meta data
		$this->meta = array_merge([
			'name' => 'app',
			'version' => '0.0.1',
			'debug' => true,
			'base_dir' => '',
			'base_url' => '',
			'ssl' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] !== 'off') : ($_SERVER['SERVER_PORT'] === 443),
			'inc' => false,
			'run' => false,
			'autoload' => true,
			'registry' => [],
			'config' => [],
		], $opts);
		//get host
		$host = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		//guess base dir?
		if(!$this->meta['base_dir']) {
			//loop through included files
			foreach(array_reverse(get_included_files()) as $f) {
				if(dirname($f) !== __DIR__) {
					$this->meta['base_dir'] = dirname($f);
					break;
				}
			}	
		}
		//guess base url?
		if(!$this->meta['base_url']) {
			//get current path
			$path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname($_SERVER['SCRIPT_FILENAME']));
			//update base url
			$this->meta['base_url'] = $host . '/' . trim($path, '/');
		}
		//is script included?
		$this->meta['inc'] = stripos($host . $_SERVER['REQUEST_URI'], $this->meta['base_url']) !== 0;
		//use autoloader?
		if($this->meta['autoload']) {
			//set dirs
			$dirs = [ $this->meta['base_dir'] . '/vendor', $libPath ];
			//loop through dirs
			foreach(array_unique($dirs) as $dir) {
				$this->autoload($dir, false);
			}
		}
		//handle errors?
		if($this->errors) {
			$this->errors->handle();
		}
	}

	public function __destruct() {
		$this->run();
	}

	public function __isset($key) {
		return isset($this->meta['registry'][$key]);
	}

	public function __get($key) {
		//registry key exists?
		if(!isset($this->meta['registry'][$key])) {
			return null;
		}
		//set vars
		$opts = [];
		$callback = $this->meta['registry'][$key];
		//remove registry entry
		unset($this->meta['registry'][$key]);
		//has config opts?
		if(isset($this->config)) {
			$opts = $this->config->get($key, []);
		} else if(isset($this->meta['config'])) {
			$opts = isset($this->meta['config'][$key]) ? $this->meta['config'][$key] : [];
		}
		//init event?
		if(isset($this->events)) {
			//Event: config opts
			$event = $this->events->dispatch($key . '.opts', $opts);
			//update opts
			$opts = $event->getParams();
		}
		//create object
		$this->$key = $callback($this, $opts);
		//merge meta config?
		if($key === 'config' && isset($this->meta['config'])) {
			//merge data
			$this->config->merge($this->meta['config'], false);
			//unset meta
			unset($this->meta['config']);
		}
		//return
		return $this->$key;
	}

	public function meta($key, $val=null) {
		if($val === null) {
			return isset($this->meta[$key]) ? $this->meta[$key] : null;
		} else {
			$this->meta[$key] = $val;
		}
	}

	public function handle($request, $response=null, $isSub=true) {
		//route request
		$request->isSub = $isSub;
		$response = $this->router->dispatch($request, $response);
		$response->isSub = $isSub;
		//get response type?
		if(!isset($response->type) || !$response->type) {
			//has content type header?
			if(!$contentType = $response->getHeaderLine('Content-Type')) {
				//check raw headers
				foreach(headers_list() as $h) {
					if(stripos($h, 'content-type') === 0) {
						$contentType = $h;
						break;
					}
				}
			}
			//parse value?
			if($contentType) {
				$contentType = str_replace('Content-Type:', '', $contentType);
				$contentType = explode(';', $contentType);
				$contentType = explode('/', $contentType[0]);
				$contentType = strtolower(trim($contentType[1]));
			}
			//set type
			$response->type = $contentType ?: 'html';
		}
		//return
		return $response;
	}

	public function run() {
		//already run?
		if($this->meta['run']) {
			return $this;
		}
		//set vars
		$app = $this;
		$response = null;
		//mark as run
		$this->meta['run'] = true;
		//EVENT: app.init
		$this->events->dispatch('app.init');
		//can update?
		if($this->meta['version']) {
			//get stored version
			$confVersion = $this->config->get('version', 0);
			//update now?
			if($confVersion < $this->meta['version']) {
				//EVENT: app.updating
				$this->events->dispatch('app.updating', [
					'from' => $confVersion,
					'to' => $this->meta['version'],
				]);
				//save new version number
				$this->config->set('version', $this->meta['version']);
			}
		}
		//can output?
		if(!$this->meta['inc']) {
			//EVENT: app.middleware
			$this->events->dispatch('app.middleware', [
				'middleware' => $this->httpMiddleware,
			]);
			//add application middleware
			$this->httpMiddleware->add(function($request) use($app) {
				return $app->handle($request, null, false);
			});
			//create request
			$request = $this->httpFactory->createFromGlobals('ServerRequest');
			//match route
			$request->isSub = false;
			$request->route = $this->router->match($request);
			//get response via middleware
			$response = $this->httpMiddleware->handle($request);
		}
		//EVENT: app.shutdown
		$this->events->dispatch('app.shutdown');
		//send response?
		if($response) {
			$response->send();
			exit();
		}
	}

	public function autoload($dir, $prepend=false) {
		//format dir
		$dir = rtrim($dir, '/');
		//is valid dir?
		if(!$dir || !is_dir($dir)) {
			return false;
		}
		//register autoloader
		return spl_autoload_register(function($class) use($dir) {
			//guess class separator
			$sep = (strpos($class, '\\') !== false) ? '\\' : '_';
			//format class path
			$file = $dir . '/' . trim(str_replace($sep, '/', $class), '/') . '.php';
			//file found?
			if(is_file($file)) {
				include($file);
			}
		}, true, $prepend);	
	}

	public function file($file, $inc=false) {
		//add file extension?
		if(strpos($file, '.') === false) {
			$file = $file . '.php';
		}
		//add file path?
		if($file[0] !== '/') {
			$file = $this->meta['base_dir'] . '/' . $file;
		}
		//does file exist?
		if(!is_file($file)) {
			return false;
		}
		//include file?
		if($inc !== false) {
			include($file);
		}
		//return
		return $file;
	}

	public function url($url=null, array $query=[], $merge=false) {
		//current url
		$host = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		$path = explode('?', $_SERVER['REQUEST_URI'])[0] . ($_GET ? '?' . http_build_query($_GET) : '');
		$url = is_null($url) ? $host . $path : $url;
		//can parse url?
		if(!is_string($url) || !$parts = parse_url($url)) {
			return null;
		}
		//create absolute url?
		if(!isset($parts['host']) || !$parts['host']) {
			if($url && $url[0] === '/') {
				$url = trim($host, '/') . '/' . trim($url, '/');
			} else {
				$url = trim($this->meta['base_url'], '/') . '/' . trim($url, '/');
			}
		}
		//set query string
		$query = $merge ? array_merge($_GET, $query) : $query;
		$url = explode('?', trim($url, '/'))[0] . ($query ? '?' . http_build_query($query) : '');
		//return
		return filter_var($url, FILTER_SANITIZE_URL);
	}

	public function redirect($url, array $opts=[]) {
		//format opts
		$opts = array_merge([
			'default' => false,
			'remote' => false,
			'code' => 302,
		], $opts);
		//create url?
		if(!$url = $this->url($url)) {
			$url = $this->url($opts['default']);
		}
		//check host?
		if($url && !$opts['remote']) {
			//start loop
			while($url) {
				//get host
				$host = parse_url($url, PHP_URL_HOST);
				//base domain matched?
				if(strpos(strrev($host), strrev($_SERVER['HTTP_HOST'])) === 0) {
					break;
				}
				//default tested?
				if(isset($tested)) {
					$url = null;
					break;
				}
				//test default
				$tested = true;
				$url = $this->url($opts['default']);
			}
		}
		//can redirect?
		if(empty($url)) {
			return;
		}
		//use meta tag?
		if(headers_sent()) {
			echo '<meta http-equiv="refresh" content="0;url=' . $url . '">';
			exit();
		}
		//set headers
		http_response_code($opts['code']);
		header('Location: ' . $url);
		exit();
	}

	public function secret($name, array $opts=[]) {
		//set opts
		$opts = array_merge([
			'type' => 'shared',
			'length' => 32,
			'expiry' => 60*60*24*30,
			'regen' => false,
		], $opts);
		//check config?
		if($opts['regen'] || !$data = $this->config->get('secrets.' . $name)) {
			//generate key
			if($opts['type'] === 'shared') {
				$data = [ 'key' => $this->crypt->nonce($opts['length']) ];
			} else {
				$data = $this->crypt->keys($opts['type']);
			}
			//set meta data
			$data['type'] = $opts['type'];
			$data['expiry'] = (int) $opts['expiry'];
			$data['created'] = time();
			//update config?
			if((isset($data['key']) && $data['key']) || (isset($data['private']) && $data['private'])) {
				$this->config->set('secrets.' . $name, $data);
			}
		}
		//has expired?
		if($data['expiry'] > 0 && ($data['created'] + $data['expiry']) < time()) {
			//regenerate key
			return $this->secret($name, [
				'type' => $data['type'],
				'length' => isset($data['key']) ? strlen($data['key']) : 32,
				'expiry' => $data['expiry'],
				'regen' => true,
			]);
		}
		//remove meta data
		unset($data['type'], $data['created'], $data['expiry']);
		//return key
		return isset($data['key']) ? $data['key'] : $data;
	}

}