<?php

namespace Bstage\Http\Middleware;

abstract class AbstractMiddleware {

	public function __invoke($request, $next) {
		return $this->process($request, $next);
	}

	abstract public function process($request, $next);

}