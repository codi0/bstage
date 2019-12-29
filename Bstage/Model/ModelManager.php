<?php

namespace Bstage\Model;

class ModelManager {

	protected $db = null;
	protected $app = null;
	protected $config = null;

	protected $cache = [];
	protected $modelClass = 'App\Model\{name}';
	protected $collectionClass = 'Bstage\Model\ModelCollection';

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __get($key) {
		return $this->get($key);
	}

	public function get($name, array $opts=[]) {
		//create model?
		if(!isset($this->cache[$name])) {
			$this->cache[$name] = $this->create($name, $opts);
		}
		//return
		return $this->cache[$name];
	}

	public function create($name, array $opts=[]) {
		//set vars
		$data = $models = [];
		//default opts
		$opts = array_merge([
			'collection' => false,
			'query' => [],
			'data' => [],
			'relations' => [],
			'class' => '',
			'table' => '',
		], $opts);
		//get singular name?
		if($opts['collection']) {
			$name = $this->name($name, [ 'singular' ]);
		}
		//check config?
		if($this->config) {
			$tmp = (array) $this->config->get('models.' . $name);
			$opts = array_merge($tmp, $opts);
		}
		//guess class?
		if(!$opts['class']) {
			$opts['class'] = str_replace('{name}', ucfirst($name), $this->modelClass);
		}
		//guess table?
		if(!$opts['table']) {
			$opts['table'] = $this->name($name, [ 'underscore' ]);
		}
		//class exists?
		if(!$opts['class'] || !class_exists($opts['class'])) {
			throw new \Exception("Model " . $opts['class'] . " not found");
		}
		//query database?
		if($opts['query'] && is_array($opts['query'])) {
			$data = (array) $this->db->select($opts['table'], $opts['query'], $opts['collection'] ? null : 1);
		}
		//check relations
		foreach($opts['relations'] as $k => $v) {
			//is object?
			if(!is_object($v)) {
				throw new \Exception("Relation $k must be an object");
			}
			//guess name?
			if(is_numeric($k)) {
				//delete old key
				unset($opts['relations'][$k]);
				//get new key
				$k = $this->name($v, [ 'camelcase' ]);
				//add new key
				$opts['relations'][$k] = $v;
			}
		}
		//add wrapper?
		if($opts['collection']) {
			$data = $data ?: $opts['data'];
		} else {
			$data = array([ 'query' => $data, 'data' => $opts['data'] ]);
		}
		//create models
		foreach($data as $d) {
			//set vars
			$changed = [];
			$class = $opts['class'];
			//convert array?
			if(!isset($d['query'])) {
				$d = array( 'query' => $d, 'data' => [] );
			}
			//check for changes?
			if(!empty($d['query'])) {
				//loop through data
				foreach($d['data'] as $k => $v) {
					//has changed?
					if(!isset($d['query'][$k]) || $d['query'][$k] !== $v) {
						$changed[] = $k;
					}
				}
			}
			//create object
			$models[] = new $class([
				'_data' => array_merge($d['query'], $d['data'], $opts['relations']),
				'_changed' => $changed,
				'_table' => $opts['table'],
				'_app' => $this->app,
			]);
		}
		//format result
		if($opts['collection']) {
			//collection wrapper
			$result = new $this->collectionClass([
				'name' => $name,
				'models' => $models,
				'relations' => $opts['relations'],
				'manager' => $this,
			]);
		} else {
			//get first model
			$result = $models[0];
		}
		//return
		return $result;
	}

	public function save() {
		foreach($this->cache as $obj) {
			$obj->save();
		}
	}

	public function name($model, array $opts=[]) {
		//is object?
		if(is_object($model)) {
			$model = get_class($model);
		}
		//parse class
		$name = explode('\\Model\\', $model, 2);
		$name = isset($name[1]) ? $name[1] : $name[0];
		$name = lcfirst($name);
		//execute methods
		foreach($opts as $method) {
			$name = $this->$method($name);
		}
		//return
		return $name;
	}

	protected function camelcase($input) {
		return str_replace('_', '', lcfirst(ucwords($input, '_')));
	}

	protected function underscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

	protected function singular($input) {
		//remove ies?
		if(substr($input, -3) === 'ies') {
			return substr($input, 0, -3) . 'y';
		}
		//remove s?
		if(substr($input, -1) === 's') {
			return substr($input, 0, -1); 
		}
		//no change
		return $input;
	}

	protected function plural($input) {
		//already plural?
		if(substr($input, -1) === 's') {
			return $input;
		}
		//add ies?
		if(substr($input, -1) === 'y') {
			$input = substr($input, 0, -1) . 'ies'; 
		}
		//add s
		return $input . 's';
	}

}