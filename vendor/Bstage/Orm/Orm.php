<?php

namespace Bstage\Orm;

class Orm {

	protected $db;
	protected $app;
	protected $config;
	protected $validator;

	protected $modelClass = '{vendor}\Model\{name}';
	protected $mapperClass = 'Bstage\Orm\Mapper';
	protected $collectionClass = 'Bstage\Orm\Collection';
	protected $proxyClass = 'Bstage\Orm\Proxy';
	protected $stateClass = 'Bstage\Db\Result\TableRow';

	protected $modelCache = [];
	protected $mapperCache = [];

	protected $idLookup = [];
	protected $mapperLookup = [];

	protected $autosave = false;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __destruct() {
		//use autosave?
		if($this->autosave && $this->mapperCache) {
			//loop through mappers
			foreach($this->mapperCache as $m) {
				$m->save();
			}
		}
	}

	public function has($model) {
		//is object?
		if(!$model || !is_object($model)) {
			return false;
		}
		//get hash
		$hash = spl_object_hash($model);
		//return
		return isset($this->modelCache[$hash]);
	}

	public function get($name, $opts=[]) {
		//set vars
		$config = [];
		$result = [];
		//set defaults
		$defaults = [
			//query definition
			'query' => [],
			'search' => [],
			'collection' => false,
			'alias' => '',
			//mapper definition
			'table' => '',
			'fields' => [],
			'data' => [],
			'relations' => [],
			//class definition
			'modelClass' => $this->modelClass,
			'mapperClass' => '',
			'collectionClass' => $this->collectionClass,
			'proxyClass' => $this->proxyClass,
			//lazy loading
			'lazy' => false,
			'parent' => null,
			'parentProp' => '',
			'autoInsert' => false,
		];
		//set config
		if($this->config) {
			$conf = $this->config->get("orm.$name") ?: [];
		}
		//is scalar input?
		if(!$opts || is_scalar($opts)) {
			$opts = [ 'query' => $opts ?: [] ];
		}
		//set query ID?
		if(array_key_exists('query', $opts) && !is_array($opts['query'])) {
			$opts['query'] = $opts['query'] ? [ 'id' => $opts['query'] ] : [];
		}
		//merge opts
		$opts = array_merge($defaults, $conf, $opts);
		//build query?
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
		//set query where clause?
		if(!$opts['query'] || !array_key_exists('where', $opts['query'])) {
			$opts['query'] = [ 'where' => $opts['query'] ? :[] ];
		}
		//get object ID
		$merged = array_merge($opts['query']['where'], (array) $opts['data']);
		$objId = (isset($merged['id']) && $merged['id']) ? ($opts['alias'] ?: $name) . $merged['id'] : null;
		//check cache?
		if($objId && isset($this->idLookup[$objId])) {
			return $this->modelCache[$this->idLookup[$objId]];
		}
		//does model class exist?
		if(!$opts['modelClass'] = $this->app->class($opts['modelClass'], $name)) {
			throw new \Exception("Model not found: $name");
		}
		//get mapper class?
		if(!$opts['mapperClass']) {
			$opts['mapperClass'] = $this->app->class('{vendor}\Model\Mapper\{name}', $name) ?: $this->mapperClass;
		}
		//is collection?
		if($opts['search']) {
			$opts['collection'] = true;
		}
		//get DB table?
		if(!$opts['table']) {
			//use mapper property?
			if(isset($opts['mapperClass']::$table) && $opts['mapperClass']::$table) {
				$opts['table'] = $opts['mapperClass']::$table;
			} else {
				//get base class
				$parent = null;
				$ref = new \ReflectionClass($opts['modelClass']);
				//loop through parents
				while($ref = $ref->getParentClass()) {
					//stop here?
					if(!$ref->isInstantiable()) break;
					//set parent
					$parent = $ref->getName();
				}
				//format parent?
				if($parent) {
					$exp = explode('\\Model\\', $parent, 2);
					$parent = isset($exp[1]) ? lcfirst($exp[1]) : null;
				}
				//set table
				$opts['table'] = $this->toUnderscore($parent ?: $name);
			}
		}
		//eager load?
		if(!$opts['lazy']) {
			//query database?
			if($opts['search']) {
				//get search vars
				$fields = isset($opts['search']['fields']) ? $opts['search']['fields'] : '';
				$term = isset($opts['search']['term']) ? $opts['search']['term'] : '';
				$score = isset($opts['search']['score']) ? $opts['search']['score'] : 0;
				//search query
				if($res = $this->db->search($opts['table'], $fields, $term, $score, $opts['query'])) {
					$opts['data'] = $res;
				}
			} elseif($opts['query']['where']) {
				//set limit
				$opts['query']['limit'] = $opts['collection'] ? null : 1;
				//select query
				if($res = $this->db->select($opts['table'], $opts['query'])) {
					$opts['data'] = $res;
				}
			}
			//wrap single row?
			if(!$opts['collection']) {
				$opts['data'] = [ $opts['data'] ];
			}
			//loop through data
			foreach($opts['data'] as $data) {
				//get object ID
				$objId = isset($data['id']) ? ($opts['alias'] ?: $name) . $data['id'] : null;
				//check cache?
				if($objId && isset($this->idLookup[$objId])) {
					return $this->modelCache[$this->idLookup[$objId]];
				}
				//unformatted data?
				if(is_array($data)) {
					//loop through array
					foreach($data as $k => $v) {
						//is relation?
						if(is_object($v)) {
							$opts['relations'][$k] = $v;
							unset($data[$k]);
						}
					}
					//create state object
					$data = new $this->stateClass([
						'db' => $this->db,
						'table' => $opts['table'],
						'primaryKey' => $opts['fields'] ? array_keys($opts['fields'])[0] : 'id',
						'data' => $data,
					]);
				}
				//get mapper class
				$mapperClass = $opts['mapperClass'];
				//create mapper
				$mapper = new $mapperClass([
					'state' => $data,
					'relations' => $opts['relations'],
					'fields' => $opts['fields'],
					'orm' => $this,
					'app' => $this->app,
					'validator' => $this->validator,
					'modelClass' => $opts['modelClass'],
					'autoInsert' => $opts['autoInsert'],
				]);
				//get model
				$model = $mapper->model();
				//get object hashes
				$modelHash = spl_object_hash($model);
				$mapperHash = spl_object_hash($mapper);
				//cache objects
				$this->modelCache[$modelHash] = $model;
				$this->mapperCache[$mapperHash] = $mapper;
				//cache lookups
				$this->mapperLookup[$modelHash] = $mapperHash;
				if($objId) $this->idLookup[$objId] = $modelHash;
				//add to result
				$result[] = $model;
			}
		}
		//is collection?
		if($opts['collection']) {
			//wrap collection?
			if($opts['collectionClass']) {
				//get class
				$class = $opts['collectionClass'];
				//create wrapper
				$result = new $class([
					'name' => $name,
					'query' => $opts['lazy'] ? $opts['query'] : [],
					'models' => $result,
					'orm' => $this,
					'autoInsert' => $opts['autoInsert'],
				]);
			}
		} else {
			//lazy load?
			if($opts['lazy']) {
				//get class
				$class = $opts['proxyClass'];
				//create proxy
				$result = new $class([
					'name' => $name,
					'query' => $opts['query'],
					'orm' => $this,
					'autoInsert' => $opts['autoInsert'],
				]);
				//add reference?
				if($opts['parent'] && $opts['parentProp']) {
					$result->addReference($opts['parent'], $opts['parentProp']);
				}
			} else {
				$result = isset($result[0]) ? $result[0] : null;
			}
		}
		//return
		return $result;
	}

	public function getAll($name, array $opts=[]) {
		return $this->get($name, array_merge($opts, [ 'collection' => true ]));
	}

	public function mapper($model) {
		//is object?
		if(!$model || !is_object($model)) {
			throw new \Exception("Model must be an object");
		}
		//get hash
		$hash = spl_object_hash($model);
		//is managed?
		if(!isset($this->mapperLookup[$hash])) {
			throw new \Exception("Model not managed");
		}
		//return
		return $this->mapperCache[$this->mapperLookup[$hash]];
	}

	public function save($model) {
		return $this->mapper($model)->save();
	}

	public function delete($model) {
		//can delete?
		if($res = $this->mapper($model)->delete()) {
			$this->detach($model);
		}
		//return
		return $res;
	}

	public function errors($model) {
		return $this->mapper($model)->errors();
	}

	public function attach($model) {
	
	}

	public function detach($model) {
	
	}

	protected function toUnderscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

}