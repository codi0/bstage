<?php

//PSR-14 compatible (without interfaces)

namespace Bstage\Event;

class Dispatcher {

	protected $provider;
	protected $app;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//create provider?
		if(!$this->provider) {
			$this->provider = new Provider;
		}
	}

	public function has($name) {
		return count($this->provider->getListenersForEvent($name)) > 0;
	}

	public function add($name, $callback) {
		$this->provider->listen($name, $callback);
	}

	public function remove($name, $callback) {
		$this->provider->unlisten($name, $callback);
	}

	public function clear($name) {
		$this->provider->unlisten($name);
	}

	public function dispatch($event, array $params=[]) {
		//create event?
		if(!is_object($event)) {
			$event = new Event($event, $params);
		}
		//loop through listeners
		foreach($this->provider->getListenersForEvent($event) as $callback) {
			//execute callback
			call_user_func($callback, $event, $this->app);
			//stop here?
			if($event->isPropagationStopped()) {
				break;
			}
		}
		//return
		return $event;
	}

}