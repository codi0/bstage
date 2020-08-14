<?php

namespace Bstage\Http\Middleware;

class NoRobots extends AbstractMiddleware {

	public function process($request, $next) {
		//get response
		$response = $next($request);
		//add header
		return $response->withHeader('X-Robots-Tag', 'noindex,nofollow');
	}

}