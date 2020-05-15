<?php

//PSR-14 compatible (without interfaces)

namespace Bstage\Event;

class Provider {

	protected $listeners = [];

	public function getListenersForEvent($event) {
		//extract name?
		if(is_object($event)) {
			$event = $event->getName();
		}
		//return
		return isset($this->listeners[$event]) ? $this->listeners[$event] : [];
	}

	public function listen($name, $listener) {
		//create array?
		if(!isset($this->listeners[$name])) {
			$this->listeners[$name] = [];
		}
		//store listener?
		if(!in_array($listener, $this->listeners[$name], true)) {
			$this->listeners[$name][] = $listener;
		}
	}

	public function unlisten($name, $listener=null) {
		//create array?
		if(!isset($this->listeners[$name])) {
			return;
		}
		//remove all?
		if($listener === null) {
			unset($this->listeners[$name]);
			return;
		}
		//loop through listeners
		foreach($this->listeners[$name] as $key => $val) {
			//listener matched?
			if($val === $listener) {
				unset($this->listeners[$name][$key]);
				break;
			}
		}
	}

}