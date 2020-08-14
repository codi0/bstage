<?php

namespace Bstage\Orm;

class Mapper {

	protected $name = '';
	protected $data = [];
	protected $with = [];
	protected $relations = [];
	protected $injectInto = [];

	protected $model;
	protected $modelClass;
	protected $autoInsert = false;

	protected $errors = [];
	protected $fields = [];

	protected $app;
	protected $orm;
	protected $validator;

	public static $table = '';

	public function __construct(array $opts=[]) {
		//loop through opts
		foreach($opts as $k => $v) {
			if($v && property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//check parent classes
		foreach(class_parents($this) as $parent) {
			//get parent properties
			$props = get_class_vars($parent);
			//merge field data
			$this->fields = array_merge($props['fields'], $this->fields);
			//set model class?
			if(!$this->modelClass) {
				$this->modelClass = $props['modelClass'];
			}
		}
		//model class set?
		if(!$this->modelClass) {
			throw new \Exception("No model class set");
		}
		//guess name?
		if(!$this->name) {
			$this->name = lcfirst(explode('\\Model\\', $this->modelClass)[1]);
		}
		//get short name
		$shortName = $this->name(true);
		//loop through fields
		foreach($this->fields as $prop => $meta) {
			//set default keys
			$this->fields[$prop] = array_merge([
				//core actions
				'column' => '', //database column name
				'filter' => '', //input filtering rules (E.g. int)
				'validate' => '', //input validation rules (E.g. optional|url)
				'save' => false, //whether to save value as state, with optional format (E.g. json, concat)
				'onHydrate' => null, //filter value before injected into model
				'onSave' => null, //filter value before saving into database
				//relation actions
				'relation' => '', //hasOne, hasMany, belongsTo, manyMany
				'bridge' => '', //table used to bridge manyMany relations
				'fk' => '', //foreign key database column name
				'lk' => '', //local key database column name
				'model' => $prop, //name of related model
				'cascade' => [], //whether relation should be saved on insert, update and delete
				'eager' => [], //whether to eager load relations with model
				'lazy' => true, //whether relation should be lazy loaded
			], $meta);
			//is relationship?
			if($rel = $this->fields[$prop]['relation']) {
				//no column
				$this->fields[$prop]['column'] = '';
				$this->fields[$prop]['save'] = false;
				//guess foreign key?
				if(!$this->fields[$prop]['fk']) {
					if($rel === 'belongsTo') {
						$this->fields[$prop]['fk'] = $this->underscore($prop) . '_id';
					} else {
						$this->fields[$prop]['fk'] = 'id';
					}
				}
				//guess local key?
				if(!$this->fields[$prop]['lk']) {
					if($rel === 'belongsTo') {
						$this->fields[$prop]['lk'] = 'id';
					} else {
						$this->fields[$prop]['lk'] = $shortName . '_id';
					}
				}
			} else {
				//no keys
				$this->fields[$prop]['fk'] = '';
				$this->fields[$prop]['lk'] = '';
				//guess column name?
				if(!$this->fields[$prop]['column']) {
					$this->fields[$prop]['column'] = $this->underscore($prop);
				}
			}
		}
	}

	public function hash() {
		return spl_object_hash($this->model());
	}

	public function name($short=false) {
		//short name?
		if($short) {
			$name = preg_split('/(?=[A-Z])/',$this->name);
			return strtolower($name[count($name)-1]);
		}
		//full name
		return $this->name;
	}

	public function fields($field=null) {
		//one field?
		if($field) {
			return isset($this->fields[$field]) ? $this->fields[$field] : null;
		}
		//all fields
		return $this->fields;
	}

	public function props($field=null) {
		//set vars
		$data = [];
		//loop through properties
		foreach((array) $this->model() as $prop => $val) {
			//format property name
			$prop = trim(str_replace([ $this->modelClass, '*' ], '', $prop));
			//return property?
			if($field === $prop) {
				return $val;
			}
			//add to array
			$data[$prop] = $val;
		}
		//return
		return $data;
	}

	public function data($field=null) {
		//one field?
		if($field) {
			return isset($this->data[$field]) ? $this->data[$field] : null;
		}
		//all fields
		return $this->data;
	}

	public function relations($field=null) {
		//one field?
		if($field) {
			return isset($this->relations[$field]) ? $this->relations[$field] : null;
		}
		//all fields
		return $this->relations;
	}

	public function errors($field=null) {
		//one field?
		if($field) {
			return isset($this->errors[$field]) ? $this->errors[$field] : null;
		}
		//all fields
		return $this->errors;
	}

	public function model() {
		//model cached?
		if($this->model) {
			return $this->model;
		}
		//format data
		$data = $this->formatData($this->data, true);
		//inject app kernel?
		if($this->app && !isset($data['app'])) {
			$data['app'] = $this->app;
		}
		//create model
		$this->model = new $this->modelClass($data);
		//add proxy reference
		foreach($this->relations as $prop => $rel) {
			if($rel instanceof Proxy) {
				$rel->__reference($this->model, $prop);
			}
		}
		//inject into
		foreach($this->injectInto as $key => $meta) {
			//unset key
			unset($this->injectInto[$key]);
			//get relation
			$rel = $this->relations[$meta['rel']];
			//inject model?
			if($mapper = $this->orm->mapper($rel)) {
				//cache model
				$res = $this->model;
				//loop through keys
				foreach($meta['keys'] as $k) {
					$res = $res->$k;
				}
				//inject result
				$mapper->inject([ $meta['prop'] => $res ]);
			}
		}
		//save now?
		if($this->autoInsert) {
			//get ID?
			if(is_object($this->data)) {
				$pkVal = $this->data->pkVal();
			} else {
				$pkVal = isset($this->data['id']) ? $this->data['id'] : null;
			}
			//can save?
			if(!$pkVal) {
				$this->save();
			}
		}
		//return
		return $this->model;
	}

	public function inject($data) {
		//set vars
		$model = $this->model();
		$data = $this->formatData($data, false);
		//model reflection
		$ref = new \ReflectionObject($model);
		//loop through data
		foreach($data as $prop => $val) {
			//inject property?
			if($ref->hasProperty($prop)) {
				$r = $ref->getProperty($prop);
				$r->setAccessible(true);
				$r->setValue($model, $val);
			}
		}
		//chain it
		return $this;
	}

	public function save(array $hashes=[]) {
		//has fields?
		if(!$this->fields) {
			return true;
		}
		//set vars
		$update = [];
		$cascade = [];
		$allowed = [];
		$isNew = true;
		$result = true;
		$hash = $this->hash();
		//already processed?
		if(in_array($hash, $hashes)) {
			return true;
		}
		//add hash
		$hashes[] = $hash;
		//reset validator?
		if($this->validator) {
			$this->validator->reset();
		}
		//has ID?
		if(is_object($this->data)) {
			$isNew = !$this->data->pkVal();
		} else {
			$isNew = !(isset($this->data['id']) && $this->data['id']);
		}
		//loop through model properties
		foreach($this->props() as $prop => $modelVal) {
			//get field meta
			$meta = isset($this->fields[$prop]) ? $this->fields[$prop] : [];
			//skip field?
			if(!$meta) continue;
			//get DB column
			$col = $meta['fk'] ?: $meta['column'];
			//get state value
			$stateVal = isset($this->data[$col]) ? $this->data[$col] : null;
			//mark as allowed?
			if(!in_array($col, $allowed)) {
				$allowed[] = $col;
			}
			//is relation?
			if($meta['relation']) {
				//cascade save?
				if($modelVal && $meta['cascade']) {
					//on insert?
					if($isNew && in_array('insert', $meta['cascade'])) {
						$cascade[] = $modelVal;
					}
					//on update?
					if(!$isNew && in_array('update', $meta['cascade'])) {
						$cascade[] = $modelVal;
					}
				}	
				//set foreign key?
				if($meta['relation'] === 'belongsTo' && is_object($modelVal)) {
					//get related ID
					$modelVal = $modelVal->id;
					//save value
					$meta['save'] = true;
				} else {
					continue;
				}
			}
			//has state changed?
			if($isNew && $stateVal) {
				$modelVal = $modelVal ?: $stateVal;
			} else if(!$isNew && $stateVal == $modelVal) {
				continue;
			}
			//filter data?
			if($this->validator && $meta['filter']) {
				$modelVal = $this->validator->filter($modelVal, $meta['filter']);
			}
			//validate data?
			if($this->validator && $meta['validate']) {
				//validation failed?
				if(!$this->validator->validate($modelVal, $meta['validate'], [ 'field' => $prop ])) {
					$result = false;
				}
			}
			//save data?
			if($result && $meta['save']) {
				$update[$col] = [
					'value' => $modelVal,
					'format' => $meta['save'],
					'onSave' => $meta['onSave'],
				];
			}
		}
		//cache errors?
		if($this->validator) {
			$this->errors = $this->validator->getErrors();
		}
		//can save?
		if($result && $update && is_object($this->data)) {
			//loop through data
			foreach($update as $k => $v) {
				//call onSave?
				if($v['onSave']) {
					$v['value'] = $this->hook($v['onSave'], $v['value']);
				}
				//convert format?
				if(is_string($v['format'])) {
					$v['value'] = $this->storeAs($v['format'], $v['value']);
				}
				//update data?
				if($v['value'] !== null) {
					$this->data[$k] = $v['value'];
				}
			}
			//save successful?
			if($result = $this->data->save($allowed)) {
				//insert ID?
				if($isNew) {
					//get ID property
					$prop = $this->property($this->data->pkCol());
					//inject ID
					$this->inject([ $prop => $result ]);
				}
				//cascade save
				foreach($cascade as $c) {
					$this->orm->save($c, $hashes);
				}
			}
		}
		//return
		return $result;
	}

	public function delete(array $hashes=[]) {
		//has fields?
		if(!$this->fields) {
			return true;
		}
		//set vars
		$hash = $this->hash();
		//already processed?
		if(in_array($hash, $hashes)) {
			return true;
		}
		//add hash
		$hashes[] = $hash;
		//delete data
		$res = $this->data->delete();
		//loop through model properties
		foreach($this->props() as $prop => $modelVal) {
			//get field meta
			$meta = isset($this->fields[$prop]) ? $this->fields[$prop] : [];
			//is relation?
			if($meta && $meta['relation']) {
				//cascade save?
				if($modelVal && $meta['cascade']) {
					//on delete?
					if(in_array('delete', $meta['cascade'])) {
						$this->orm->delete($modelVal, $hashes);
					}
				}
			}
		}	
		//return
		return $res;
	}

	protected function formatData($data, $createRel=true) {
		//set vars
		$res = [];
		$relations = [];
		//loop through data
		foreach($data as $key => $val) {
			//get property
			$prop = $this->property($key);
			//get meta data
			$meta = $prop ? $this->fields[$prop] : [];
			//is relation?
			if($meta && $meta['relation']) {
				//relation found?
				if($rel = $this->formatRelation($prop, $val, false)) {
					$res[$prop] = $this->relations[$prop] = $rel;
				}
				//next
				continue;
			}
			//convert format?
			if($meta && is_string($meta['save'])) {
				$val = $this->storeAs($meta['save'], $val, true);
			}			
			//add to array?
			if($val !== null) {
				//call onHydrate?
				if($meta && $meta['onHydrate']) {
					$val = $this->hook($meta['onHydrate'], $val);
				}
				//guess property?
				if(!$prop) {
					$prop = $this->camelcase($key);
				}
				//store value
				$res[$prop] = $val;
			}
		}
		//create relations?
		if($createRel) {
			//create new relations
			foreach($this->fields as $prop => $meta) {
				//is relation?
				if(!$meta['relation']) {
					continue;
				}
				//relation exists?
				if(isset($this->relations[$prop]) && $this->relations[$prop]) {
					continue;
				}
				//get value
				$val = isset($this->data[$meta['fk']]) ? $this->data[$meta['fk']] : null;
				//create relation
				$this->relations[$prop] = $this->formatRelation($prop, $val, true);
			}
			//merge relations
			$res = array_merge($res, $this->relations);
		}
		//return
		return $res;
	}

	protected function formatRelation($prop, $val, $create=false) {
		//valid relation?
		if(!isset($this->fields[$prop]) || !$this->fields[$prop]['relation']) {
			return null;
		}
		//fetch relation?
		if(($val || $create) && !is_object($val)) {
			//set vars
			$relQuery = [];
			$relData = [];
			$meta = $this->fields[$prop];
			$collection = (stripos($meta['relation'], 'many') !== false);
			$with = isset($this->with[$prop]) ? $this->with[$prop] : [];
			//run query?
			if(is_scalar($val)) {
				//add where
				$relQuery = [
					'where' => [
						$meta['lk'] => $val,
					]
				];
			} else if($val) {
				$relData = $this->extractSelf($prop, $val);
			}
			//add bridge table?
			if($meta['relation'] === 'manyMany') {
				$relQuery['join'] = [
					'table' => $meta['bridge'],
					'on' => [
						'local' => $meta['lk'],
						'foreign' => $meta['fk'],
					]
				];
			}
			//get relation
			$val = $this->orm->get($meta['model'], $this->arrayMergeRecursive([
				'query' => $relQuery,
				'data' => $relData,
				'collection' => $collection,
				'eager' => $meta['eager'],
				'lazy' => $meta['lazy'],
			], $with));
		}
		//return
		return $val;
	}

	protected function extractSelf($prop, array $data) {
		//loop through data
		foreach($data as $k => $v) {
			//recursive?
			if(is_array($v)) {
				$data[$k] = $this->extractSelf($prop, $v);
				continue;
			}
			//found self?
			if(is_string($v) && strpos($v, '<SELF') === 0) {
				//get keys
				$keys = trim(str_replace([ '<SELF', '>' ], '', $v), '.');
				$keys = $keys ? explode('.', $keys) : [];
				//model exists?
				if($this->model) {
					//cache model
					$res = $this->model;
					//loop through keys
					foreach($keys as $k) {
						$res = $res->$k;
					}
					//update value
					$data[$k] = $res;
				} else {
					//cache reference
					$this->injectInto[] = [
						'rel' => $prop,
						'prop' => $k,
						'keys' => $keys,
					];
					//remove key
					unset($data[$k]);
				}
			}
		}
		//return
		return $data;
	}

	protected function storeAs($type, $value, $reverse=false) {
		//select type
		if($type === 'concat') {
			if($reverse) {
				$value = $value ? explode(',', $value) : [];
			} else {
				$value = trim(implode(',', $value));
			}
		} else if($type === 'json') {
			if($reverse) {
				$value = json_decode($value, true);
			} else {
				$value = json_encode($value);
			}
		}
		//return
		return $value;
	}

	protected function column($input) {
		//exact match?
		if(isset($this->fields[$input])) {
			return $this->fields[$input]['column'];
		}
		//check secondary keys
		foreach($this->fields as $prop => $meta) {
			if($meta['column'] === $input) {
				return $input;
			}
		}
		//not found
		return null;
	}

	protected function property($input) {
		//exact match?
		if(isset($this->fields[$input])) {
			return $input;
		}
		//check secondary keys
		foreach($this->fields as $prop => $meta) {
			if($meta['column'] === $input) {
				return $prop;
			}
		}
		//not found
		return null;
	}

	protected function underscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

	protected function camelcase($input) {
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
	}

	protected function hook($callback, $value) {
		//parse callback?
		if(is_string($callback) && strpos($callback, '.') !== false) {
			//set vars
			$exp = explode('.', $callback, 2);
			$name = $exp[0];
			$method = isset($exp[1]) ? $exp[1] : '';
			if($name === 'mapper') {
				$callback = [ $this, $method ];
			} else if(isset($this->app->$name)) {
				$callback = [ $this->app->$name, $method ];
			}
		}
		//return
		return call_user_func($callback, $value, $this);
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