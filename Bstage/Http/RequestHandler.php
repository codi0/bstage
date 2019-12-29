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

	public function add($middleware) {
		$this->middlewares[] = $middleware;
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
		if($middleware instanceof \Closure) {
			return call_user_func($middleware, $request, $this);
		}
		return $middleware->process($request, $this);
	}

	public function process($request, $handler) {
        $this->handler = $handler;
        return $this->handle($request);
	}

}