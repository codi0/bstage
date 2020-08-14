<?php

namespace Bstage\Route;

class Dispatcher {

	protected $routes = [];
	protected $filters = [];

	protected $app;
	protected $httpFactory;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function add($route, $method, $callback=null) {
		//set vars
		$prefix = '';
		$methods = [];
		$isClass = false;
		//valid callback?
		if($callback === null) {
			$callback = $method;
			$method = null;
		}
		//loop through methods
		foreach((array) $method as $m) {
			if($m) $methods[] = strtoupper($m);
		}
		//prefix found?
		if(strpos($route, '!') !== false) {
			list($prefix, $route) = explode('!', $route, 2);
		}
		//run filters
		$route = $this->runFilters($route);
		//create array?
		if(!isset($this->routes[$route])) {
			$this->routes[$route] = [];
		}
		//save route
		$this->routes[$route][] = [
			'name' => $route,
			'methods' => $methods,
			'callback' => $callback,
			'prefix' => $prefix,
		];
		//chain it
		return $this;
	}

	public function match($request) {
		//set vars
		$params = [];
		$route = null;
		$default = null;
		$method = $request->getMethod();
		$uri = trim($request->getUri()->getPathInfo(), '/');
		//put exact match first?
		if(isset($this->routes[$uri])) {
			$this->routes = [ $uri => $this->routes[$uri] ] + $this->routes;
		}
		//loop through routes
		foreach($this->routes as $name => $items) {
			//loop through items
			foreach($items as $item) {
				//reset params
				$params = [];
				//method match?
				if($item['methods'] && !in_array($method, $item['methods'])) {
					continue;
				}
				//default match?
				if($name == '404' || $name == '*') {
					$default = $item;
					continue;
				}
				//run filters
				$name = $this->runFilters($name);
				//exact match?
				if($name == $uri) {
					$route = $item;
					break 2;
				}
				//set vars
				$count = 0;
				$total = count(explode('<:', $name)) - 1;
				$regex = str_replace([ '/<', '>/', '/' ], [ '<', '>', '\/' ], $name);
				//has params?
				if($total > 0) {
					//replace params
					$regex = preg_replace_callback('/<:([a-z0-9]+)(\?)?>/i', function($matches) use(&$params, &$count, $total) {
						//counter
						$count++;
						//set vars
						$before = ($count == 1) ? '?' : '';
						$after = ($count == $total) ? '\/?' : '';
						$isId = strtolower(substr($matches[1], -2)) === 'id';
						$pattern = $isId ? '[0-9]+' : '[^\/]+';
						//add key
						$params[$matches[1]] = $isId ? 0 : '';
						//replace with regex
						return '(\/' . $before . $pattern . $after . ')' . (isset($matches[2]) ? '?' : '');
					}, $regex);
				}
				//pattern match?
				if(!preg_match_all('/^' . $regex . '$/', $uri, $matches)) {
					continue;
				}
				//remove overall match
				array_shift($matches);
				//loop through params
				foreach($params as $k => $v) {
					//get value
					$tmp = trim(array_shift($matches)[0], '/');
					//add param
					$params[$k] = ($v === 0) ? (int) $tmp : $tmp;
					//remove next
					array_shift($matches);
				}
				//set route
				$route = $item;
				break 2;
			}
		}
		//use default?
		if($route === null) {
			//set default
			$route = $default;
			//failed?
			if($route === null) {
				throw new \Exception("No 404 route found");
			}
		}
		//controller class?
		if(is_string($route['callback']) && !function_exists($route['callback'])) {
			//parse callback
			$exp = explode('@', $route['callback'], 2);
			$class = $exp[0];
			$route['callback'] = new $class([ 'app' => $this->app ]);
			//default method?
			if(!isset($exp[1]) && !is_callable($route['callback'])) {
				$exp[1] = 'index';
			}
			//use method?
			if(isset($exp[1]) && $exp[1]) {
				$route['callback'] = [ $route['callback'], lcfirst($exp[1]) ];
			}
		}
		//set params
		$route['params'] = $params;
		//return route
		return new Route($route);
	}

	public function call($route, $request=null) {
		//find route?
		if(is_string($route)) {
			//create request?
			if(!$request) {
				$uri = '/' . trim($route, '/');
				$request = $this->httpFactory->createServerRequest($_SERVER['REQUEST_METHOD'], $uri);
			}
			//match request
			$route = $this->match($request);
		} else {
			//create request?
			if(!$request) {
				$request = $this->httpFactory->createFromGlobals('ServerRequest');
			}
		}
		//valid route?
		if(!($route instanceof Route)) {
			return false;
		}
		//has response?
		if(!$response = $request->getAttribute('response')) {
			//create response
			$response = $this->httpFactory->createResponse();
			//link response to request
			$request = $request->withAttribute('response', $response);
		}
		//link route to request
		$request = $request->withAttribute('route', $route);
		//start buffer
		ob_start();
		//invoke callback
		$res = call_user_func($route->getCallback(), $request, $response, $this->app);
		//end buffer
		$buffer = ob_get_clean();
		//is response object?
		if(is_object($res) && strpos(get_class($res), 'Response') !== false) {
			return $res;
		}
		//is string?
		if(is_string($res) || (is_object($res) && method_exists($res, '__toString'))) {
			$response->getBody()->write($res);
		} else if($buffer) {
			$response->getBody()->write($buffer);
		}
		//return
		return $response;
	}

	public function dispatch($request) {
		//match route
		$route = $this->match($request);
		//call route
		return $this->call($route, $request);
	}

	public function addFilter($from, $to) {
		//add filter
		$this->filters[] = [
			'from' => $from,
			'to' => $to,
		];
		//chain it
		return $this;
	}

	public function runFilters($route) {
		//loop through filters
		foreach($this->filters as $filter) {
			$regex = '/' . str_replace('/', '\/', $filter['from']) . '(?!\.)/';
			$route = preg_replace($regex, $filter['to'], $route);
		}
		//return
		return trim($route, '/');
	}

}