<?php

namespace Bstage\Orm;

class Mapper {

	protected $state;
	protected $model;
	protected $modelClass;
	protected $autoInsert = false;

	protected $errors = [];
	protected $fields = [];
	protected $relations = [];

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
			if(!$this->modelClass && $props['modelClass']) {
				$this->modelClass = $props['modelClass'];
			}
		}
		//model class set?
		if(!$this->modelClass) {
			throw new \Exception("No model class set");
		}
		//loop through fields
		foreach($this->fields as $prop => $meta) {
			//set default keys
			$this->fields[$prop] = array_merge([
				//core actions
				'column' => '', //database column name (or foreign key column)
				'filter' => '', //input filtering rules (E.g. int)
				'validate' => '', //input validation rules (E.g. optional|url)
				'save' => false, //whether to save value as state, with optional format (E.g. json, concat)
				'onHydrate' => null, //filter value before injected into model
				'onSave' => null, //filter value before saving into database
				//relation actions
				'relation' => '', //hasOne, hasMany, belongsTo, manyMany
				'model' => $prop, //name of related model
				'lazy' => true, //whether relation should be lazy loaded
			], $meta);
			//is relationship?
			if($rel = $this->fields[$prop]['relation']) {
				//never save
				$this->fields[$prop]['save'] = false;
				//guess column name?
				if(!$this->fields[$prop]['column']) {
					if($rel === 'belongsTo') {
						$this->fields[$prop]['column'] = $this->underscore($prop) . '_id';
					} else {
						$this->fields[$prop]['column'] = 'id';
					}
				}
			} else {
				//guess column name?
				if(!$this->fields[$prop]['column']) {
					$this->fields[$prop]['column'] = $this->camelcase($prop);
				}
			}
		}
	}

	public function errors($field=null) {
		//one field?
		if($field) {
			return isset($this->errors[$field]) ? $this->errors[$field] : null;
		}
		//all fields
		return $this->errors;
	}

	public function fields($prop=null) {
		//one field?
		if($prop) {
			return isset($this->fields[$prop]) ? $this->fields[$field] : null;
		}
		//all fields
		return $this->fields;
	}

	public function state($key=null) {
		//one field?
		if($key) {
			return isset($this->state[$key]) ? $this->state[$key] : null;
		}
		//all fields
		return $this->state;
	}

	public function props($withRelations=true) {
		//set vars
		$data = [];
		//loop through properties
		foreach((array) $this->model() as $prop => $modelVal) {
			//skip property?
			if(!$withRelations && is_object($modelVal)) {
				continue;
			}
			//format property name
			$prop = trim(str_replace([ $this->modelClass, '*' ], '', $prop));
			//add to array
			$data[$prop] = $modelVal;
		}
		//return
		return $data;
	}

	public function model(array $relations=[]) {
		//model cached?
		if($this->model) {
			return $this->model;
		}
		//set vars
		$data = $proxies = [];
		$idVal = $pkCol = $state = null;
		//has state?
		if($this->state) {
			$idVal = $this->state->pkVal();
			$pkCol = $this->state->pkCol();
			$state = $this->state->toArray();
		}
		//loop through fields
		foreach($this->fields as $prop => $meta) {
			//get DB column
			$col = $meta['column'];
			//get state value
			$stateVal = isset($state[$col]) ? $state[$col] : null;
			//convert format?
			if(is_string($meta['save'])) {
				$stateVal = $this->storeAs($meta['save'], $stateVal, true);
			}
			//is relation?
			if($meta['relation']) {
				//check for pre-configured relation
				if(isset($this->relations[$prop]) && $this->relations[$prop]) {
					$stateVal = $this->relations[$prop];
				} else if(isset($relations[$prop]) && $relations[$prop]) {
					$stateVal = $relations[$prop];
				} else {
					//set vars
					$query = [];
					//query by foreign key?
					if($meta['relation'] === 'belongsTo') {
						if($stateVal) {
							$query[$pkCol] = $stateVal;
						}
					} else {
						if($idVal) {
							$query[$col] = $idVal;
						}
					}
					//get relation
					$stateVal = $this->orm->get($meta['model'], [
						'query' => $query,
						'collection' => (stripos($meta['relation'], 'many') !== false),
						'lazy' => $meta['lazy'],
					]);
					//cache lazy relation?
					if($stateVal instanceOf Proxy) {
						$proxies[] = [
							'object' => $stateVal,
							'parentProp' => $prop,
						];
					}
				}
			}
			//continue?
			if($stateVal !== null) {
				//call onHydrate?
				if($meta['onHydrate']) {
					$stateVal = $this->hook($meta['onHydrate'], $stateVal);
				}
				//add to array
				$data[$prop] = $stateVal;
			}
		}
		//inject app kernel?
		if($this->app && !isset($data['app'])) {
			$data['app'] = $this->app;
		}
		//create model
		$this->model = new $this->modelClass($data);
		//set proxy references
		foreach($proxies as $p) {
			$p['object']->addReference($this->model, $p['parentProp']);
		}
		//set Id now?
		if($this->autoInsert && !$idVal) {
			$this->save();
		}
		//return
		return $this->model;
	}

	public function inject($data, $skipEmpty=true) {
		//set vars
		$model = $this->model();
		$ref = new \ReflectionObject($model);
		//loop through data
		foreach($data as $k => $v) {
			//skip empty value?
			if($skipEmpty && ($v === '' || $v === null)) {
				continue;
			}
			//property name
			$prop = $this->property($k);
			//get meta
			$meta = isset($this->fields[$prop]) ? $this->fields[$prop] : null;
			//call onHydrate?
			if($meta && $meta['onHydrate']) {
				$v = $this->hook($meta['onHydrate'], $v);
			}
			//inject property?
			if($v !== null) {
				$r = $ref->getProperty($prop);
				$r->setAccessible(true);
				$r->setValue($model, $v);
			}
		}
		//chain it
		return $this;
	}

	public function save() {
		//set vars
		$state = [];
		$update = [];
		$allowed = [];
		$isNew = true;
		$result = true;
		//has fields?
		if(!$this->fields) {
			return $result;
		}
		//reset validator?
		if($this->validator) {
			$this->validator->reset();
		}
		//has state?
		if($this->state) {
			$isNew = !$this->state->pkVal();
			$state = $this->state->toArray();
		}
		//loop through model properties
		foreach($this->props() as $prop => $modelVal) {
			//get field meta
			$meta = isset($this->fields[$prop]) ? $this->fields[$prop] : [];
			//skip field?
			if(!$meta) continue;
			//get DB column
			$col = $meta['column'];
			//cache column
			$allowedCols[] = $col;
			//get state value
			$stateVal = isset($state[$col]) ? $state[$col] : null;
			//is relation?
			if($meta['relation']) {
				//set foreign key?
				if($meta['relation'] === 'belongsTo' && $modelVal) {
					//get related ID
					$modelVal = $modelVal->id;
					//save value
					$meta['save'] = true;
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
			if($this->state && $result && $meta['save']) {
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
		if($result && $update) {
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
				//update state?
				if($v['value'] !== null) {
					$this->state[$k] = $v['value'];
				}
			}
			//build list of allowed columns
			foreach($this->fields as $prop => $meta) {
				if($meta['column']) {
					$allowed[] = $meta['column'];
				}
			}
			//save successful?
			if($result = $this->state->save($allowed)) {
				if($isNew) {
					//get model ID
					$idVal = $this->state->pkVal();
					$idProp = $this->property($this->state->pkCol());
					//inject ID
					$this->inject([ $idProp => $idVal ]);
				}
			}
		}
		//return
		return $result;
	}

	public function delete() {
		return $this->state ? $this->state->delete() : true;
	}

	protected function hook($callback, $value) {
		//parse callback?
		if(is_string($callback) && strpos($callback, '.') !== false) {
			//set vars
			$exp = explode('.', $callback, 2);
			$name = $exp[0];
			$method = isset($exp[1]) ? $exp[1] : '';
			if($name === 'model') {
				$callback = [ $this->model, $method ];
			} elseif($name === 'mapper') {
				$callback = [ $this, $method ];
			} else {
				$callback = [ $this->app->$name, $method ];
			}
		}
		//return
		return call_user_func($callback, $value, $this);
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

	protected function column($property) {
		//column defined?
		if(isset($this->fields[$property]) && $this->fields[$property]['column']) {
			return $this->fields[$property]['column'];
		}
		//best guess
		return $this->underscore($property);
	}

	protected function property($column) {
		//check fields
		foreach($this->fields as $property => $meta) {
			if($meta['column'] === $column) {
				return $property;
			}
		}
		//best guess
		return $this->camelcase($column);
	}

	protected function underscore($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

	protected function camelcase($input) {
		return preg_replace_callback('/[A-Z]/', function($match) {
			return '_' . strtolower($match[0]);
		}, $input);	
	}

}