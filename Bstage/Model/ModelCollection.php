<?php

namespace Bstage\Model;

class ModelCollection implements \ArrayAccess, \Iterator {

	protected $name = '';
	protected $models = [];
	protected $relations = [];

	protected $manager = null;

	protected $position = 0;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//reset pointer
		$this->rewind();
	}

	public function __get($key) {
		//is parent?
		if(isset($this->relations[$key])) {
			return $this->relations[$key];
		}
	}

	public function findBy($field, $value) {
		//loop through models
		foreach($this->models as $model) {
			//field match found?
			if(isset($model->$field) && $model->$field == $value) {
				return $model;
			}
		}
	}

	public function get($key) {
		return isset($this->models[$key]) ? $this->models[$key] : null;
	}

	public function add($model) {
		//create model from data array?
		if($this->name && $this->manager && is_array($model)) {
			//create from manager
			$model = $this->manager->create($this->name, [
				'data' => array_merge($model, $this->relations),
			]);
		}
		//is object?
		if(!is_object($model)) {
			throw new \Exception('Model must be an object');
		}
		//check for dupe?
		if($modelId = $model->id) {
			//loop through models
			foreach($this->models as $k => $v) {
				//model ID found?
				if($v->id == $modelId) {
					return $v;
				}
			}
		}
		//add to array
		$this->models[] = $model;
		//return
		return $model;
	}

	public function remove($model) {
		//loop through models
		foreach($this->models as $k => $v) {
			//match found?
			if($v === $model) {
				//call delete method?
				if(method_exists($this->models[$k], 'delete')) {
					$this->models[$k]->delete();
				}
				//remove from array
				unset($this->models[$k]);
			}
		}
		//return
		return true;
	}

	public function save() {
		foreach($this->models as $obj) {
			$obj->save();
		}
	}

	public function offsetExists($key) {
		return array_key_exists($key, $this->models);
	}

    public function offsetGet($key) {
		return $this->get($key);
	}

	public function offsetSet($key, $val) {
		return $this->add($val);
	}

	public function offsetUnset($key) {
		return $this->remove($val);
	}

    public function key() {
        return $this->position;
    }

    public function current() {
        return $this->models[$this->position];
    }

    public function valid() {
        return isset($this->models[$this->position]);
    }

    public function next() {
        ++$this->position;
    }

    public function rewind() {
        $this->position = 0;
    }

}