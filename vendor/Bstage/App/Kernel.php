<?php

namespace Bstage\App;

class Kernel {

	private $meta = [];

	public function __construct(array $opts=[]) {
		//set meta data
		$this->meta = array_merge([
			'name' => 'app',
			'version' => '0.0.1',
			'debug' => true,
			'ssl' => null,
			'base_url' => '',
			'base_url_org' => '',
			'base_dir' => '',
			'autoload' => true,
			'autoload_paths' => [],
			'services' => [],
			'config' => [],
			'inc' => false,
			'run' => false,
		], $opts);
		//guess ssl?
		if($this->meta['ssl'] === null) {
			$this->meta['ssl'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] !== 'off') : ($_SERVER['SERVER_PORT'] === 443);
		}
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
		//get current host
		$host = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		//guess base url?
		if(!$this->meta['base_url']) {
			//get current path
			$path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname($_SERVER['SCRIPT_FILENAME']));
			//update base url
			$this->meta['base_url'] = $host . '/' . trim($path, '/');
		}
		//cache org url
		$this->meta['base_url_org'] = $this->meta['base_url'];
		//use autoloader?
		if($this->meta['autoload']) {
			//set dirs
			$dirs = [
				dirname(get_included_files()[0]) . '/vendor', //last loaded dir
				$this->meta['base_dir'] . '/vendor', //base dir
				dirname(dirname(__DIR__)) //library dir
			];
			//loop through dirs
			foreach(array_unique($dirs) as $dir) {
				$this->autoload($dir, false);
			}
		}
		//basic XSS protection
		foreach([ 'REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO' ] as $key) {
			if(isset($_SERVER[$key])) {
				$_SERVER[$key] = $this->validator->filter($_SERVER[$key]);
			}
		}
		//is script included?
		$this->meta['inc'] = stripos($host . $_SERVER['REQUEST_URI'], $this->meta['base_url']) !== 0;
		//handle errors
		$this->errorHandler->handle();
	}

	public function __isset($key) {
		return isset($this->meta['services'][$key]);
	}

	public function __get($key) {
		//found in service registry?
		if(!isset($this->meta['services'][$key])) {
			return null;
		}
		//set vars
		$opts = [];
		$callback = $this->meta['services'][$key];
		//remove from service registry
		unset($this->meta['services'][$key]);
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
		//init services
		if(is_string($callback)) {
			$opts['app'] = $this;
			$this->$key = new $callback($opts);
		} else {
			$this->$key = call_user_func($callback, $opts, $this);
		}
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

	public function __set($key, $val) {
		//save as live service?
		if(is_object($val) && !($val instanceof \Closure)) {
			$this->$key = $val;
			return;
		}
		//save in service registry?
		if(is_callable($val)) {
			$this->meta['services'][$key] = $val;
			return;
		}
		//invalid property
		throw new \Exception("Service must be a defined as a callable or an object: $key");
	}

	public function meta($key, $val=null) {
		if($val === null) {
			return isset($this->meta[$key]) ? $this->meta[$key] : null;
		} else {
			$this->meta[$key] = $val;
		}
	}

	public function handle($request) {
		return $this->router->dispatch($request);
	}

	public function run($scope=null) {
		//already run?
		if($this->meta['run']) {
			return $this;
		}
		//mark as run
		$this->meta['run'] = true;
		$this->meta['scope'] = $scope;
		//loop through modules
		foreach($this->config->get('modules', []) as $name => $scope) {
			//valid scope?
			if($scope && $this->meta['scope']) {
				if(strpos($scope, $this->meta['scope']) === false) {
					continue;
				}
			}
			//loop through loader paths
			foreach($this->meta['autoload_paths'] as $path) {
				//is another module path?
				if(strpos($path, '/modules/') !== false) {
					continue;
				}
				//get module folder
				$folder = dirname($path) . '/modules/' . $name;
				//boostrap file found?
				if(is_file($folder . '/Bootstrap.php')) {
					//set autoload path
					$this->autoload($folder . '/vendor');
					//add template path?
					if($this->templates) {
						$this->templates->addPath($folder . '/templates');
					}
					//load module
					(function($app, $__dir) {
						include($__dir . '/Bootstrap.php');
					})($this, $folder);
				}
			}
		}
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
			//create request
			$request = $this->httpFactory->createFromGlobals('ServerRequest');
			//match route
			$route = $this->router->match($request);
			//store request attributes
			$request = $request->withAttribute('primary', true)
							   ->withAttribute('route', $route)
							   ->withAttribute('app', $this);
			//EVENT: app.request
			$event = $this->events->dispatch('app.request', [
				'request' => $request,
				'middleware' => $this->httpMiddleware,
			]);
			//store updated request
			$this->request = $event->request;
			//add application middleware
			$this->httpMiddleware->add('app.core', [ $this, 'handle' ]);
			//execute middleware stack
			$response = $this->httpMiddleware->handle($this->request);
			//EVENT: app.response
			$event = $this->events->dispatch('app.response', [
				'request' => $this->request,
				'response' => $response,
			]);
			//store updated response
			$response = $event->response;
		}
		//send response?
		if(isset($response) && $response) {
			$response->send();
		}
		//EVENT: app.shutdown
		$this->events->dispatch('app.shutdown');
	}

	public function autoload($dir, $prepend=false) {
		//format dir
		$dir = rtrim($dir, '/');
		//valid dir?
		if(!$dir || in_array($dir, $this->meta['autoload_paths']) || !is_dir($dir)) {
			return false;
		}
		//save path
		if($prepend) {
			array_unshift($this->meta['autoload_paths'], $dir);
		} else {
			$this->meta['autoload_paths'][] = $dir;
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

	public function class($class, $name='') {
		//set vars
		$classes = [];
		$prefix = explode('\\', $class)[0];
		$vendors = [ ucfirst($this->meta['name']), 'Bstage' ];
		//add name?
		if(!empty($name)) {
			$name = str_replace(' ', '', ucwords(str_replace([ '-', '_' ], ' ', $name)));
			$class = str_replace('{name}', ucfirst($name), $class);
		}
		//check vendors?
		if(in_array($prefix, $vendors)) {
			$classes[] = $class;
		} else {
			//loop through vendors
			foreach($vendors as $vendor) {
				//format class name
				if(strpos($class, '{vendor}') !== false) {
					$classes[] = str_replace('{vendor}', $vendor, $class);
				} else {
					$classes[] = $vendor . '\\' . $class;
				}
			}
		}
		//does class exist?
		foreach($classes as $c) {
			if(class_exists($c)) {
				return $c;
			}
		}
		//not found
		return null;
	}

	public function file($file, $value=false) {
		//add file extension?
		if(strpos($file, '.') === false) {
			$file = $file . '.php';
		}
		//add file path?
		if($file[0] !== '/') {
			$file = $this->meta['base_dir'] . '/' . $file;
		}
		//perform operation?
		if($value === true && is_file($file)) {
			//include file
			include($file);
		} else if(is_string($value)) {
			//save file
			$dir = dirname($file);
			if(!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			file_put_contents($file, $value);
		}
		//return
		return $file;
	}

	public function url($url=null, $query=null, $merge=false) {
		//get host
		$host = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		//use current url?
		if($url === null) {
			$path = explode('?', $_SERVER['REQUEST_URI'])[0];
			$qs = ($_GET && is_null($query)) ? '?' . http_build_query($_GET) : '';
			$url = $host . $path . $qs;
		}
		//can parse url?
		if(!is_string($url) || !$parts = parse_url($url)) {
			return null;
		}
		//create absolute url?
		if(!isset($parts['host']) || !$parts['host']) {
			if($url && $url[0] === '/') {
				$url = trim($host, '/') . '/' . trim($url, '/');
			} else {
				$isFile = strpos($url, '.') !== false;
				$url = trim($this->meta[$isFile ? 'base_url_org' : 'base_url'], '/') . '/' . trim($url, '/');
			}
		}
		//merge query parts?
		if(isset($parts['query']) && $parts['query']) {
			parse_str($parts['query'], $tmp);
			$query = array_merge($tmp, $query ?: []);
		}
		//merge $_GET?
		if($merge && $_GET) {
			$query = array_merge($_GET, $query ?: []);
		}
		//build final url
		$url = explode('?', trim($url, '/'))[0] . ($query ? '?' . http_build_query($query) : '');
		//return
		return filter_var($url, FILTER_SANITIZE_URL);
	}

	public function redirect($url, array $query=[], $merge=false) {
		//merge $_GET?
		if($merge && $_GET) {
			$query = array_merge($_GET, $query);
		}
		//remove redirect param?
		if($url !== 'login' && isset($query['redirect'])) {
			unset($query['redirect']);
		}
		//generate url
		$url = $this->url($url, $query);
		$host = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		//valid host?
		if(strpos($url, $host) !== 0) {
			$url = $this->url(null);
		}
		//send header
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

	public function config($key, $val='[NULL]') {
		if($val === '[NULL]') {
			return $this->config->get($key);
		} else {
			return $this->config->set($key, $val);
		}
	}

	public function input($name, $method=null) {
		return $this->input->find($method, $name);
	}

	public function route($route, $method=null, $callback=null) {
		//using route callback?
		if($callback && is_callable($callback)) {
			$method = $callback;
			$callback = null;
		}
		//add or call?
		if(is_string($method) || is_callable($method)) {
			return $this->router->add($route, $method, $callback);
		} else {
			return $this->router->call($route, $method, $callback);
		}
	}

	public function http($uri, array $opts=[]) {
		return $this->httpClient->send($uri, $opts);
	}

	public function form($name, $method='post', $action='') {
		return $this->formFactory->create($name, $method, $action);
	}

	public function model($name, $opts=[]) {
		return $this->orm->get($name, $opts);
	}

	public function view($name, array $params=[]) {
		return $this->templates->render($name, $params);
	}

}