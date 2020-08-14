<?php

namespace Bstage\App;

class Kernel {

	private $meta = [];

	public function __construct(array $opts=[]) {
		//set defaults
		$this->meta = array_merge([
			'name' => 'app',
			'type' => '',
			'timezone' => 'UTC',
			'debug' => true,
			'ssl' => null,
			'host' => '',
			'path_info' => '',
			'base_url' => '',
			'base_url_org' => '',
			'base_dir' => '',
			'app_dir' => '',
			'config_dir' => '',
			'inc_paths' => [],
			'module_paths' => [],
			'modules' => [],
			'services' => [],
			'config' => [],
			'autoload' => true,
			'inc' => false,
			'run' => false,
		], $opts);
		//set app type?
		if(!$this->meta['type']) {
			$this->meta['type'] = (stripos($this->meta['name'], 'api') !== false) ? 'api' : 'web';
		}
		//set base dir?
		if(!$this->meta['base_dir']) {
			//loop through included files
			foreach(array_reverse(get_included_files()) as $f) {
				if(dirname($f) !== __DIR__) {
					$this->meta['base_dir'] = dirname($f);
					break;
				}
			}	
		}
		//set app dir?
		if(!$this->meta['app_dir']) {
			$this->meta['app_dir'] = $this->meta['base_dir'];
		}
		//set config dir?
		if(!$this->meta['config_dir']) {
			$this->meta['config_dir'] = $this->meta['app_dir'] . '/modules/' . $this->meta['name'] . '/config';
		}
		//set include paths
		$this->meta['inc_paths'] = array_unique([ $this->meta['base_dir'], dirname(dirname(dirname(__DIR__))) ]);
		//get base path
		$basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname($_SERVER['SCRIPT_FILENAME']));
		$reqUri = explode('?', $_SERVER['REQUEST_URI'])[0];
		//set path info
		$this->meta['path_info'] = $_SERVER['PATH_INFO'] = rtrim(str_replace($basePath, '', $reqUri), '/');
		//delete orig path info?
		if(isset($_SERVER['ORIG_PATH_INFO'])) {
			unset($_SERVER['ORIG_PATH_INFO']);
		}
		//guess ssl?
		if($this->meta['ssl'] === null) {
			$this->meta['ssl'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] !== 'off') : ($_SERVER['SERVER_PORT'] === 443);
		}
		//set host
		$this->meta['host'] = 'http' . ($this->meta['ssl'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
		//guess base url?
		if(!$this->meta['base_url']) {
			//update base url
			$this->meta['base_url'] = $this->meta['host'] . '/' . trim($basePath, '/');
		}
		//cache org url
		$this->meta['base_url_org'] = $this->meta['base_url'];
		//is script included?
		$this->meta['inc'] = stripos($this->meta['host'] . $reqUri, $this->meta['base_url']) !== 0;
		//set timezone?
		if($this->meta['timezone']) {
			date_default_timezone_set($this->meta['timezone']);
		}
		//register autoloader?
		if($this->meta['autoload']) {
			spl_autoload_register([ $this, 'autoload' ]);
		}
		//load app module
		$this->module($this->meta['name'], [
			'path' => $this->meta['app_dir'],
		]);
		//handle errors?
		if(isset($this->errorHandler)) {
			$this->errorHandler->handle();
		}
	}

	public function __isset($key) {
		return isset($this->meta['services'][$key]);
	}

	public function __get($key) {
		//found in service registry?
		if(!isset($this->meta['services'][$key])) {
			return (property_exists($this, $key) && $key !== 'meta') ? $this->$key : null;
		}
		//set vars
		$opts = [];
		$callback = $this->meta['services'][$key];
		//remove from service registry
		unset($this->meta['services'][$key]);
		//has config opts?
		if(isset($this->config)) {
			$opts = $this->config->get($key) ?: [];
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
		//create service
		if(is_string($callback)) {
			$res = new $callback(array_merge($opts, [ 'app' => $this ]));
		} else {
			$res = call_user_func($callback, $opts, $this);
		}
		//cache service
		$this->$key = $res;
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

	public function handle($request) {
		return $this->router->dispatch($request);
	}

	public function run() {
		//already run?
		if($this->meta['run']) {
			return $this;
		}
		//mark as run
		$this->meta['run'] = true;
		//EVENT: app.init
		$this->events->dispatch('app.init');
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

	public function module($name, array $opts=[]) {
		//load module?
		if(!isset($this->meta['modules'][$name])) {
			//prevent race condition
			$this->meta['modules'][$name] = false;
			//merge opts
			$opts = array_merge([
				'required' => true,
				'uri' => '',
				'path' => '',
			], $opts);
			//valid uri match?
			if($opts['uri'] && strpos($this->meta['path_info'], $opts['uri']) === false) {
				return false;
			}
			//get include paths
			$incPaths = (array) ($opts['path'] ?: $this->meta['inc_paths']);
			//loop through include paths
			foreach($incPaths as $path) {
				//get module path
				$path = $path . '/modules/' . $name;
				//get module class
				$class = ucfirst($name) . '\\Module';
				//load bootstrap?
				if(!is_file($path . '/Module.php')) {
					continue;
				}
				//load module file
				include_once($path . '/Module.php');
				//cache path
				$this->meta['module_paths'][$name] = $path;
				//init module
				$this->meta['modules'][$name] = new $class([
					'app' => $this,
					'dir' => $path,
				]);
				//stop here
				break;
			}
			//module found?
			if(!$this->meta['modules'][$name] && $opts['required']) {
				throw new \Exception("Module not found: $name");
			}
		}
		//return
		return $this->meta['modules'][$name];
	}

	public function service($key, $val=null) {
		//get service?
		if($val === null) {
			return $this->__get($key);
		}
		//set service?
		if(is_callable($val) || is_string($val)) {
			//add to registry
			$this->meta['services'][$key] = $val;
		} else {
			//remove old service?
			if(isset($this->meta['services'][$key])) {
				unset($this->meta['services'][$key]);
			}
			//add as service
			$this->$key = $val;
		}
	}

	public function config($key, $val='[NULL]') {
		if($val === '[NULL]') {
			return $this->config->get($key);
		} else {
			return $this->config->set($key, $val);
		}
	}

	public function route($route, $method=null, $callback=null) {
		//using route callback?
		if($callback === null) {
			$callback = $method;
			$method = null;
		}
		//add or call?
		if($callback && (is_callable($callback) || is_string($callback))) {
			return $this->router->add($route, $method, $callback);
		} else {
			return $this->router->call($route, $callback);
		}
	}

	public function http($uri, array $opts=[]) {
		return $this->httpClient->send($uri, $opts);
	}

	public function middleware($name, $callback) {
		return $this->httpMiddleware->add($name, $callback);
	}

	public function event($name, $callback=[]) {
		if(is_callable($callback)) {
			return $this->events->add($name, $callback);
		} else {
			return $this->events->dispatch($name, $callback);
		}
	}

	public function model($name, $opts=[]) {
		return $this->orm->get($name, $opts);
	}

	public function view($name, array $params=[]) {
		return $this->templates->render($name, $params);
	}

	public function input($name, $method=null) {
		return $this->input->find($method, $name);
	}

	public function form($name, $method='post', $action='') {
		return $this->formFactory->create($name, $method, $action);
	}

	public function file($file, $value=null) {
		//set vars
		$paths = [];
		//is absolute path?
		if($file[0] === '/') {
			//one path
			$paths[] = $file;
		} else {
			//add potential paths
			foreach($this->meta['module_paths'] as $path) {
				$paths[] = $path . '/' . $file;
			}
		}
		//set value?
		if(is_string($value)) {
			//get parent dir
			$dir = dirname($paths[0]);
			//create dir?
			if(!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			//update file
			return file_put_contents($paths[0], $value);
		}
		//loop through paths
		foreach($paths as $path) {
			//file exists?
			if($value !== false && !is_file($path)) {
				continue;
			}
			//get file content?
			if($value === true) {
				return file_get_contents($path);
			}
			//get path
			return $path;
		}
		//not found
		return false;
	}

	public function url($url=null, $query=null, $merge=false) {
		//use current url?
		if($url === null) {
			$path = explode('?', $_SERVER['REQUEST_URI'])[0];
			$qs = ($_GET && is_null($query)) ? '?' . http_build_query($_GET) : '';
			$url = $this->meta['host'] . $path . $qs;
		}
		//is local file?
		if($url && $url[0] === '/') {
			//remove base dir
			$url = str_replace($this->meta['base_dir'] . '/', '', $url);
			//stop here?
			if($url[0] === '/' && strpos($url, '.') !== false) {
				return null;
			}
		}
		//can parse url?
		if(!is_string($url) || !$parts = parse_url($url)) {
			return null;
		}
		//add redirect?
		if($url === 'login' && $query === null) {
			$query = [ 'redirect' => $_SERVER['REQUEST_URI'] ];
		}
		//create absolute url?
		if(!isset($parts['host']) || !$parts['host']) {
			if($url && $url[0] === '/') {
				$url = trim($this->meta['host'], '/') . '/' . trim($url, '/');
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
		//run filters?
		if(isset($this->router)) {
			$url = $this->router->runFilters($url);
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
		//valid host?
		if(strpos($url, $this->meta['host']) !== 0) {
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

	public function class($class, $name='') {
		//set vars
		$classes = [];
		//parse name?
		if($name && strpos($name, '@') !== false) {
			list($vendor, $name) = explode('@', $name, 2);
			$class = str_replace('{vendor}', ucfirst($vendor), $class);
		}
		//replace name?
		if($name && strpos($class, '{name}') !== false) {
			$name = str_replace(' ', '', ucwords(str_replace([ '-', '_' ], ' ', $name)));
			$class = str_replace('{name}', ucfirst($name), $class);
		}
		//check vendors?
		if(strpos($class, '{vendor}') !== false) {
			//get vendors
			$vendors = array_keys($this->meta['module_paths']);
			$vendors = array_merge($vendors, [ explode('\\', __NAMESPACE__)[0] ]);
			//loop through vendors
			foreach($vendors as $vendor) {
				$classes[] = str_replace('{vendor}', ucfirst($vendor), $class);
			}
		} else {
			//set class
			$classes[] = $class;
		}
		//loop through classes
		foreach($classes as $class) {
			//class exists?
			if(class_exists($class)) {
				return $class;
			}
		}
		//not found
		return null;
	}

	protected function autoload($class) {
		//set vars
		$paths = [];
		$sep = (strpos($class, '\\') !== false) ? '\\' : '_';
		$file = trim(str_replace($sep, '/', $class), '/') . '.php';
		//add vendor paths
		foreach($this->meta['inc_paths'] as $path) {
			$paths[] = $path . '/vendor/' . $file;
		}
		//add module paths
		foreach($this->meta['module_paths'] as $name => $path) {
			//module match found?
			if(stripos($file, $name . '/') === 0) {
				//build file path
				$filePath = $path . '/src/' . str_replace(ucfirst($name) . '/', '', $file);
				//prepend to array
				array_unshift($paths, $filePath);
				//stop here
				break;
			}
		}
		//loop through paths
		foreach($paths as $path) {
			//match found?
			if(is_file($path)) {
				include($path);
				break;
			}
		}
	}

}