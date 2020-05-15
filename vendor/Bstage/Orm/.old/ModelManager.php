<?php

namespace Bstage\Model;

class ModelManager {

	protected $db = null;
	protected $app = null;
	protected $config = null;

	protected $cache = [];
	protected $modelClass = '{vendor}\Model\{name}';
	protected $collectionClass = 'Bstage\Model\ModelCollection';

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __destruct() {
		//sync all models
		foreach($this->cache as $key => $model) {
			$model->save();
		}
	}

	public function get($name, $opts=[]) {
		//convert to array?
		if(!$opts || is_scalar($opts)) {
			$id = [ 'id' => $opts ];
			$opts = $opts ? [ 'query' => $id ] : [];
		}
		//set defaults
		$defaults = [
			'data' => [], //already queried data
			'query' => [], //where query clause
			'search' => [], //fulltext search clause
			'relations' => [], //related models
			'collection' => false, //singular model or collection
			'class' => $this->modelClass, //model class
			'table' => '', //database table
		];
		//merge options
		$opts = array_merge($defaults, $opts);
		//add to query?
		if(!$opts['query']) {
			//check option keys
			foreach($opts as $k => $v) {
				//is default option?
				if(!in_array($k, array_keys($defaults))) {
					$opts['query'][$k] = $v;
					unset($opts[$k]);
				}
			}
		}
		//format query?
		if(!$opts['query'] || !isset($opts['query']['where'])) {
			$opts['query'] = [ 'where' => $opts['query'] ];
		}
		//set vars
		$data = $models = [];
		$merged = array_merge($opts['query']['where'], $opts['data']);
		$objId = (isset($merged['id']) && $merged['id']) ? $name . $merged['id'] : null;
		//check cache?
		if($objId && isset($this->cache[$objId])) {
			return $this->cache[$objId];
		}
		//is search collection?
		if($opts['search']) {
			$opts['collection'] = true;
		}
		//get singular name?
		if($opts['collection']) {
			$name = $this->formatName($name, 'singular');
		}
		//check config?
		if($this->config) {
			$tmp = (array) $this->config->get('models.' . $name);
			$opts = array_merge($tmp, $opts);
		}
		//guess table?
		if(!$opts['table']) {
			$opts['table'] = $this->formatName($name, 'underscore');
		}
		//does class exist?
		if(!$opts['class'] = $this->app->class($opts['class'], $name)) {
			throw new \Exception("Model not found: $name");
		}
		//query database?
		if($opts['search']) {
			//get score
			$score = isset($opts['search']['score']) ? $opts['search']['score'] : 0;
			//search query
			$data = $this->db->search($opts['table'], $opts['search']['fields'], $opts['search']['term'], $score, $opts['query']);
		} elseif($opts['query']['where']) {
			//set limit
			$opts['query']['limit'] = $opts['collection'] ? null : 1;
			//select query
			$data = $this->db->select($opts['table'], $opts['query']);
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
				$k = $this->formatName($v, 'camelcase');
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
			//get obj ID
			$merged = array_merge((array) $d['query'], (array) $d['data'], (array) $opts['relations']);
			$objId = (isset($merged['id']) && $merged['id']) ? $name . $merged['id'] : null;
			//check cache?
			if($objId && isset($this->cache[$objId])) {
				$obj = $this->cache[$objId];
			} else {
				//create object
				$obj = new $class([
					'_data' => $merged,
					'_changed' => $changed,
					'_table' => $opts['table'],
					'_app' => $this->app,
				]);
				//cache?
				if($objId) {
					$this->cache[$objId] = $obj;
				}
			}
			//add to models
			$models[] = $obj;
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

	public function add($model) {
		//valid model?
		if(!$model || !isset($model->__key) || !$model->__key) {
			throw new \Exception("Invalid model object");
		}
		//add to cache
		$this->cache[$model->__key] = $model;
		//return
		return true;
	}

	public function remove($model) {
		//valid model?
		if(!$model || !isset($model->__key) || !$model->__key) {
			throw new \Exception("Invalid model object");
		}
		//delete model?
		if(isset($this->cache[$model->__key])) {
			unset($this->cache[$model->__key]);
		}
		//return
		return true;
	}

	public function save($model) {
		return $model->save();
	}

	public function delete($model) {
		return $model->delete();
	}

	public function formatName($model, $modifiers='') {
		//is object?
		if(is_object($model)) {
			$model = get_class($model);
		}
		//parse class
		$name = explode('\\Model\\', $model, 2);
		$name = isset($name[1]) ? $name[1] : $name[0];
		$name = lcfirst($name);
		//execute modifiers
		foreach(explode('|', $modifiers) as $m) {
			//format method
			$m = '_rule' . ucfirst($m);
			//method exists?
			if(method_exists($this, $m)) {
				$name = $this->$m($name);
			}
		}
		//return
		return $name;
	}

	protected function _ruleCamelcase($input) {
		return str_replace('_', '', lcfirst(ucwords($input, '_')));
	}

	protected function _ruleUnderscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

	protected function _ruleSingular($input) {
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

	protected function _rulePlural($input) {
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