<?php

namespace Bstage\View\Form;

class FormFactory {

	protected $cache = [];

	protected $orm;
	protected $html;
	protected $input;
	protected $events;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function get($name) {
		//does form exist?
		if(!isset($this->cache[$name])) {
			throw new \Exception("Form not created: $name");
		}
		return $this->cache[$name];
	}

	public function create($name, $method='post', $action='') {
		//does form exist?
		if(!isset($this->cache[$name])) {
			//format opts
			if(is_array($method)) {
				$opts = $method;
			} else {
				$opts = [ 'method' => $method, 'action' => $action ];
			}
			//add objects
			$opts['orm'] = $this->orm;
			$opts['html'] = $this->html;
			$opts['input'] = $this->input;
			$opts['events'] = $this->events;
			//create form
			$this->cache[$name] = new Form($name, $opts);
		}
		//return
		return $this->cache[$name];
	}

}