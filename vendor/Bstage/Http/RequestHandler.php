<?php

//PSR-15 compatible (without interfaces)

namespace Bstage\Http;

class RequestHandler {

	protected $middlewares = [];
	protected $handler;

	public function __construct(array $opts=[]) {
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __invoke($request) {
		return $this->handle($request);
	}

	public function add($name, $middleware, $prepend=false) {
		if($prepend) {
			$this->middlewares = [ $name => $middleware ] + $this->middlewares;
		} else {
			$this->middlewares[$name] = $middleware;
		}
		return $this;
	}

	public function remove($name) {
		if(isset($this->middlewares[$name])) {
			unset($this->middlewares[$name]);
		}
		return $this;
	}

	public function handle($request) {
		if(!$middleware = current($this->middlewares)) {
			if($this->handler) {
				return $this->handler->handle($request);
			}
			return new Response;
		}
		next($this->middlewares);
		if(is_callable($middleware)) {
			return call_user_func($middleware, $request, $this);
		}
		if(is_string($middleware)) {
			$middleware = new $middleware;
		}
		return $middleware->process($request, $this);
	}

	public function process($request, $handler) {
        $this->handler = $handler;
        return $this->handle($request);
	}

}