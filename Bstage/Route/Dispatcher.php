<?php

namespace Bstage\Route;

class Dispatcher {

	protected $routes = [];

	protected $context;
	protected $httpFactory;
	protected $cachedRequest;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//check routes
		foreach($this->routes as $route => $meta) {
			//format input
			$method = isset($meta['method']) ? $meta['method'] : null;
			$methods = isset($meta['methods']) ? $meta['methods'] : $method;
			$callback = isset($meta['callback']) ? $meta['callback'] : null;
			//add route
			$this->add($route, $methods, $callback);
		}
	}

	public function add($route, $method, $callback=null) {
		//set vars
		$methods = [];
		$route = trim($route, '/');
		//valid callback?
		if($method && is_callable($method)) {
			$callback = $method;
			$method = null;
		} elseif(!is_callable($callback)) {
			throw new \Exception('Invalid route callback');
		}
		//loop through methods
		foreach((array) $method as $m) {
			if($m) $methods[] = strtoupper($m);
		}
		//save route
		$this->routes[$route] = [
			'methods' => $methods,
			'callback' => $callback,
		];
		//chain it
		return $this;
	}

	public function match($request) {
		//set vars
		$route = null;
		$default = null;
		$params = [];
		$method = $request->getMethod();
		$uri = trim($request->getUri()->getPathInfo(), '/');
		//put exact match first?
		if(isset($this->routes[$uri])) {
			$this->routes = [ $uri => $this->routes[$uri] ] + $this->routes;
		}
		//loop through routes
		foreach($this->routes as $key => $val) {
			//reset params
			$params = [];
			//method matched?
			if($val['methods'] && !in_array($method, $val['methods'])) {
				continue;
			}
			//exact match?
			if($key === $uri) {
				$route = $key;
				break;
			}
			//default match?
			if($key === '*') {
				$default = $key;
				continue;
			}
			//set vars
			$count = 0;
			$total = count(explode('<:', $key)) - 1;
			$regex = str_replace([ '/<', '>/', '/' ], [ '<', '>', '\/' ], $key);
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
			$route = $key;
			break;
		}
		//use default?
		if($route === null) {
			$route = $default;
		}
		//return route
		return new Route($route, $params);
	}

	public function call($route, $request=null, $response=null) {
		//set vars
		$route = trim($route, '/');
		//route matched?
		if(!isset($this->routes[$route])) {
			return false;
		}
		//set request?
		if(!$request && $this->cachedRequest) {
			$request = $this->cachedRequest;
			$request->route = null;
		}
		//set response?
		if(!$response) {
			$response = $this->httpFactory->createResponse();
		}
		//set route?
		if(!$request->route) {
			$request->route = new Route($route);
		}
		//start buffer
		ob_start();
		//invoke callback
		$res = call_user_func($this->routes[$route]['callback'], $request, $response, $this->context);
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

	public function dispatch($request, $response=null) {
		//match route?
		if(!isset($request->route) || !$request->route) {
			$request->route = $this->match($request);
		}
		//cache request
		$this->cachedRequest = $request;
		//call route
		return $this->call($request->route->getName(), $request, $response);
	}

}