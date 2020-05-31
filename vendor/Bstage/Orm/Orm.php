<?php

namespace Bstage\Orm;

class Orm {

	protected $db;
	protected $app;
	protected $validator;

	protected $modelClass = '{vendor}\Model\{name}';
	protected $mapperClass = 'Bstage\Orm\Mapper';
	protected $collectionClass = 'Bstage\Orm\Collection';
	protected $proxyClass = 'Bstage\Orm\Proxy';
	protected $dataClass = 'Bstage\Db\Result\TableRow';

	protected $modelCache = [];
	protected $mapperCache = [];

	protected $modelLookup = [];
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
		$result = [];
		//set defaults
		$defaults = [
			//query definition
			'finder' => '',
			'query' => [],
			'with' => [],
			'collection' => false,
			//mapper definition
			'alias' => '',
			'table' => '',
			'fields' => [],
			'data' => [],
			//class definition
			'modelClass' => $this->modelClass,
			'mapperClass' => '',
			'collectionClass' => $this->collectionClass,
			'proxyClass' => $this->proxyClass,
			//load definitions
			'eager' => [],
			'lazy' => false,
			'parent' => null,
			'parentProp' => '',
			'autoInsert' => false,
		];
		//is scalar input?
		if(!$opts || is_scalar($opts)) {
			$opts = [ 'query' => $opts ?: [] ];
		}
		//set query?
		if(!isset($opts['query'])) {
			$opts['query'] = [];
		} else if(!is_array($opts['query'])) {
			$opts['query'] = $opts['query'] ? [ 'id' => $opts['query'] ] : [];
		}
		//extract query?
		if(!$opts['query']) {
			//loop through options
			foreach($opts as $k => $v) {
				//not default?
				if(!isset($defaults[$k])) {
					$opts['query'][$k] = $v;
					unset($opts[$k]);
				}
			}
		}
		//has query?
		$hasQuery = !!$opts['query'];
		//set query where clause?
		if(!$opts['query'] || !array_key_exists('where', $opts['query'])) {
			//reserved keys
			$reserved = [ 'fields', 'table', 'join', 'where', 'search', 'group', 'having', 'order', 'limit', 'offset' ];
			//loop through query
			foreach($opts['query'] as $k => $v) {
				//add to where clause?
				if(!in_array($k, $reserved)) {
					//create where clause?
					if(!isset($opts['query']['where'])) {
						$opts['query']['where'] = [];
					}
					//add where key
					$opts['query']['where'][$k] = $v;
					//remove query key
					unset($opts['query'][$k]);
				}
			}
		}
		//merge defaults
		$opts = array_merge($defaults, $opts);
		//use finder?
		if($opts['finder'] || ($hasQuery && $opts['finder'] !== false)) {
			//get mapper class?
			if(!$opts['mapperClass']) {
				$opts['mapperClass'] = $this->app->class('{vendor}\Model\Mapper\{name}', $name) ?: $this->mapperClass;
			}
			//get finder method
			$mapperClass = $opts['mapperClass'];
			$customFinder = $opts['finder'] && is_string($opts['finder']);
			$finderMethod = 'find' . ($customFinder ? ucfirst($opts['finder']) : 'Default');
			//does method exist?
			if(method_exists($mapperClass, $finderMethod)) {
				$opts['query'] = $this->arrayMergeRecursive($mapperClass::$finderMethod(), $opts['query']);
			} else if($customFinder) {
				throw new \Exception("Method not found: $mapperClass::$finderMethod");
			}
		}
		//hash object query
		$objQuery = $this->createHash('query', $name, $opts['data'], $opts);
		//check model lookup?
		if($objQuery && isset($this->modelLookup[$objQuery])) {
			//is proxy?
			if($this->modelLookup[$objQuery] instanceof Proxy) {
				if($tmp = $this->modelLookup[$objQuery]->__object()) {
					$this->modelLookup[$objQuery] = $tmp;
				}
			}
			//return cache
			return $this->modelLookup[$objQuery];
		}
		//does model class exist?
		if(!$opts['modelClass'] = $this->app->class($opts['modelClass'], $name)) {
			throw new \Exception("Model not found: $name");
		}
		//get mapper class?
		if(!$opts['mapperClass']) {
			$opts['mapperClass'] = $this->app->class('{vendor}\Model\Mapper\{name}', $name) ?: $this->mapperClass;
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
				$opts['table'] = $this->underscore($parent ?: $name);
			}
		}
		//force eager?
		if($opts['data']) {
			$opts['lazy'] = false;
		}
		//eager load?
		if(!$opts['lazy']) {
			//query database?
			if($opts['query']) {
				//set limit?
				if(!$opts['collection'] || !isset($opts['query']['limit'])) {
					$opts['query']['limit'] = $opts['collection'] ? null : 1;
				}
				//select query
				if($res = $this->db->select($opts['table'], $opts['query'])) {
					$opts['data'] = $res;
				}
			}
			//wrap data in array?
			if(!$opts['collection'] || ($opts['data'] && !isset($opts['data'][0]))) {
				$opts['data'] = [ $opts['data'] ];
			}
			//eager load relations
			foreach($opts['eager'] as $rel) {
				//set vars
				$in = [];
				$tmp = [];
				//guess column name
				$col = preg_split('/(?=[A-Z])/', $rel);
				$col = strtolower($col[count($col)-1]) . '_id';
				//get values to query
				foreach($opts['data'] as $data) {
					if(isset($data[$col]) && $data[$col]) {
						if(!in_array($data[$col], $in)) {
							$in[] = $data[$col];
						}
					}
				}
				//stop here?
				if(empty($in)) {
					continue;
				}
				//create models
				$objs = $this->get($rel, [
					'collection' => true,
					'query' => [
						'id' => $in,
					],
				]);
				//format models
				foreach($objs as $o) {
					$tmp[$o->id] = $o;
				}
				//loop through data
				foreach($opts['data'] as $data) {
					//add object as relation?
					if(isset($tmp[$data[$col]])) {
						$data[$rel] = $tmp[$data[$col]];
					}
				}
			}
			//loop through data
			foreach($opts['data'] as $data) {
				//hash object ID
				$objId = $this->createHash('id', $name, $data, $opts);
				//check model lookup?
				if($objId && isset($this->modelLookup[$objId])) {
					//is proxy?
					if($this->modelLookup[$objId] instanceof Proxy) {
						if($tmp = $this->modelLookup[$objId]->__object()) {
							$this->modelLookup[$objId] = $tmp;
						}
					}
					//return cache
					return $this->modelLookup[$objId];
				}
				//is raw data?
				if(is_array($data)) {
					//create data object
					$data = new $this->dataClass([
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
					'data' => $data,
					'with' => $opts['with'],
					'fields' => $opts['fields'],
					'orm' => $this,
					'app' => $this->app,
					'validator' => $this->validator,
					'modelClass' => $opts['modelClass'],
					'autoInsert' => $opts['autoInsert'],
				]);
				//create model
				$model = $mapper->model();
				//get object hashes
				$modelHash = spl_object_hash($model);
				$mapperHash = spl_object_hash($mapper);
				//cache lookups
				$this->modelCache[$modelHash] = $model;
				$this->mapperCache[$mapperHash] = $mapper;
				$this->mapperLookup[$modelHash] = $mapper;
				//add ID lookups?
				if($objQuery) $this->modelLookup[$objQuery] = $model;
				if($objId) $this->modelLookup[$objId] = $model;
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
					'models' => $result,
					'orm' => $this,
					'ormOpts' => $opts['lazy'] ? $opts : [],
				]);
				//cache lookup?
				if($objQuery) {
					$this->modelLookup[$objQuery] = $result;
				}
			}
		} else {
			//lazy load?
			if($opts['lazy']) {
				//get class
				$class = $opts['proxyClass'];
				//create proxy
				$result = new $class([
					'name' => $name,
					'orm' => $this,
					'ormOpts' => $opts,
				]);
				//cache lookup?
				if($objQuery) {
					$this->modelLookup[$objQuery] = $result;
				}
			} else {
				$result = isset($result[0]) ? $result[0] : null;
			}
		}
		//return
		return $result;
	}

	public function getAll($name, array $opts=[]) {
		//force collection
		$opts['collection'] = true;
		//return
		return $this->get($name, $opts);
	}

	public function mapper($model) {
		//is object?
		if(!$model || !is_object($model)) {
			throw new \Exception("Model must be an object");
		}
		//is collection?
		if($model instanceof Collection) {
			return $model;
		}
		//get hash
		$hash = spl_object_hash($model);
		//is managed?
		if(!isset($this->mapperLookup[$hash])) {
			throw new \Exception("Model not managed");
		}
		//return
		return $this->mapperLookup[$hash];
	}

	public function save($model, array $hashes=[]) {
		return $this->mapper($model)->save($hashes);
	}

	public function delete($model, array $hashes=[]) {
		return $this->mapper($model)->delete($hashes);
	}

	public function errors($model) {
		return $this->mapper($model)->errors();
	}

	protected function underscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

	protected function createHash($type, $name, $data, $opts) {
		//set vars
		$name = $opts['alias'] ?: $name;
		$collection = $opts['collection'] ? 1 : 0;
		$lazy = $opts['lazy'] ? 1 : 0;
		$id = (isset($data['id']) && $data['id']) ? $data['id'] : null;
		//convert ID?
		if(empty($id)) {
			$id = ($type === 'query') ? $opts['query'] : [];
		} elseif(is_array($id) && count($id) == 1) {
			$id = [ 'id' => $id[0] ];
		} else {
			$id = [ 'id' => $id ];
		}
		//create hash?
		if(!empty($id)) {
			$id = md5($type . $name . $collection . $lazy . serialize($id));
		}
		//return
		return $id ?: null;
	}

	protected function arrayMergeRecursive(array $arr1, array $arr2) {
		//source empty?
		if(empty($arr1)) {
			return $arr2;
		}
		//loop through 2nd array
		foreach($arr2 as $k => $v) {
			//next level?
			if(is_array($v)) {
				//does key exist?
				if(isset($arr1[$k]) && is_array($arr1[$k])) {
					//add value
					$arr1[$k] = $this->arrayMergeRecursive($arr1[$k], $v);
					//next
					continue;
				}
			}
			//change value?
			if($v !== null) {
				//set value
				$arr1[$k] = $v;
			} elseif(isset($arr1[$k])) {
				//delete value
				unset($arr1[$k]);
			}
		}
		//return
		return $arr1;
	}

}