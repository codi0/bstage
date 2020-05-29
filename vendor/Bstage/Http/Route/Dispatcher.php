<?php

namespace Bstage\Http\Route;

class Dispatcher {

	protected $routes = [];
	protected $filters = [];

	protected $context;
	protected $httpFactory;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//check routes
		foreach($this->routes as $route => $meta) {
			//unset route
			unset($this->routes[$route]);
			//format input
			$method = isset($meta['method']) ? $meta['method'] : null;
			$methods = isset($meta['methods']) ? $meta['methods'] : $method;
			$callback = isset($meta['callback']) ? $meta['callback'] : null;
			//add route
			$this->add($route, $methods, $callback);
		}
	}

	public function has($route) {
		//run filters
		$route = $this->runFilters($route);
		//check if route exists
		return isset($this->routes[$route]);
	}

	public function add($route, $method, $callback=null) {
		//set vars
		$prefix = '';
		$methods = [];
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
		//prefix found?
		if(strpos($route, '!') !== false) {
			list($prefix, $route) = explode('!', $route, 2);
		}
		//run filters
		$route = $this->runFilters($route);
		//save route
		$this->routes[$route] = [
			'methods' => $methods,
			'callback' => $callback,
			'prefix' => $prefix,
		];
		//chain it
		return $this;
	}

	public function remove($route) {
		//run filters
		$route = $this->runFilters($route);
		//route exists?
		if(isset($this->routes[$route])) {
			unset($this->routes[$route]);
		}
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
			//default match?
			if($key == '404' || $key == '*') {
				$default = $key;
				continue;
			}
			//run filters
			$key = $this->runFilters($key);
			//exact match?
			if($key === $uri) {
				$route = $key;
				break;
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
			//set default
			$route = $default;
			//failed?
			if($route === null) {
				throw new \Exception("No 404 route found");
			}
		}
		//get prefix
		$prefix = isset($this->routes[$route]) ? $this->routes[$route]['prefix'] : '';
		//return route
		return new Route($route, $params, $prefix);
	}

	public function call($route, $request=null, $response=null) {
		//set vars
		$route = $this->runFilters($route);
		//route matched?
		if(!isset($this->routes[$route])) {
			return false;
		}
		//create request?
		if(!$request) {
			$request = $this->httpFactory->createFromGlobals('ServerRequest');
		}
		//link route?
		if(!$request->getAttribute('route')) {
			$request = $request->withAttribute('route', new Route($route));
		}
		//create response?
		if(!$response) {
			$response = $this->httpFactory->createResponse();
		}
		//link response
		$request = $request->withAttribute('response', $response);
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
		if(!$request->getAttribute('route')) {
			$request = $request->withAttribute('route', $this->match($request));
		}
		//call route
		return $this->call($request->getAttribute('route')->getName(), $request, $response);
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
			$route = str_replace($filter['from'], $filter['to'], $route);
		}
		//return
		return trim($route, '/');
	}

}