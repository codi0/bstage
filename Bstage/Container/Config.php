<?php

//PSR-11 compatible (without interfaces)

namespace Bstage\Container;

class Config {

	protected $data = [];
	protected $dataLocal = [];

	protected $path = '';
	protected $files = [];
	protected $permissions = 0640;

	protected $token = '.';
	protected $readonly = false;

	public function __construct(array $opts=[]) {
		//format opts?
		if(!isset($opts['data']) && !isset($opts['path'])) {
			$opts = [ 'data' => $opts ];
		}
		//set properties
		foreach($opts as $k => $v) {
			if($k === 'data') {
				$this->dataLocal = $v;
			} elseif(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//load data?
		if($this->path || $this->files) {
			$this->load();
		}
	}

	public function has($id) {
		return $this->get($id) !== null;
	}

	public function get($id, $default=null) {
		//set vars
		$idArr = $id ? explode($this->token, $id) : [];
		$data = $this->deepMerge($this->data, $this->dataLocal);
		//loop through segments
		foreach($idArr as $index => $seg) {
			//is array?
			if(is_array($data) && array_key_exists($seg, $data)) {
				$data = $data[$seg];
				continue;
			}
			//is object?
			if(is_object($data) && isset($data->$seg)) {
				$data = $data->$seg;
				continue;
			}
			//not found
			return $default;
		}
		//return
		return $data;
	}

	public function set($id, $value, $save=true) {
		//read only?
		if($this->readonly) {
			throw new \Exception('Data is read only');
		}
		//set vars
		$arr = []; $tmp =& $arr; $data = $value;
		$idArr = $id ? explode($this->token, $id) : [];
		$source = $save ? $this->data : $this->dataLocal;
		//valid input?
		if(!$idArr && !is_array($value)) {
			throw new \Exception('Value must be an array, if no key set');
		}
		//merge data?
		if(!empty($idArr)) {
			//loop through segments
			foreach($idArr as $index => $seg) {
				if($index+1 < count($idArr)) {
					$tmp[$seg] = [];
					$tmp =& $tmp[$seg];
				} else {
					$tmp[$seg] = $value;
				}
			}
			//merge data
			$data = $this->deepMerge($source, $arr, true);
		}
		//data changed?
		if($source !== $data) {
			//save?
			if($save) {
				$this->data = $data;
				$this->save();
			} else {
				$this->dataLocal = $data;
			}
		}
		//return
		return true;
	}

	public function delete($id, $save=true) {
		return $this->set($id, null, $save);
	}

	public function clear($save=true) {
		return $this->set(null, [], $save);
	}

	public function merge(array $data, $save=true) {
		return $this->set(null, $data, $save);
	}

	public function toArray() {
		return $this->deepMerge($this->data, $this->dataLocal);
	}

	public function save() {
		//read only?
		if($this->readonly) {
			throw new \Exception('Data is read only');
		}
		//set vars
		$coreData = $this->data;
		//loop through files
		foreach($this->files as $key => $file) {
			//remove from core?
			if(isset($coreData[$key]) && is_array($coreData[$key])) {
				$data = $coreData[$key];
				unset($coreData[$key]);
			} else {
				$data = [];
			}
			//save file
			$res = file_put_contents($file, "<?php\n\n return " . var_export($data, true) . ';', LOCK_EX);
			//set file permissions?
			if($res && $this->permissions) {
				chmod($file, $this->permissions);
			}
		}
		//save core data?
		if($this->path) {
			//save file
			$res = file_put_contents($this->path, "<?php\n\n return " . var_export($coreData, true) . ';', LOCK_EX);
			//set file permissions?
			if($res && $this->permissions) {
				chmod($this->path, $this->permissions);
			}
		}
		//return
		return true;
	}

	protected function load() {
		//format path
		$this->path = rtrim(str_replace('\\', '/', $this->path), '/');
		//is file?
		if($this->path && is_file($this->path)) {
			//get data
			$tmp = include($this->path);
			//is array?
			if(is_array($tmp)) {
				$this->data = $tmp;
			}
		}
		//is dir?
		if($this->path && is_dir($this->path)) {
			//scan for php file matches
			foreach(glob($this->path . "/*.php") as $file) {
				$this->files[basename($file, '.php')] = $file;
			}
			//update path
			$this->path = $this->path . '/core.php';
		}
		//load files
		foreach($this->files as $key => $file) {
			//get data
			$tmp = include($file);
			//is array?
			if(is_array($tmp)) {
				//wrap data?
				if(!isset($tmp[$key]) && $key !== 'core') {
					$tmp = [ $key => $tmp ];
				}
				//merge data
				$this->data = array_merge($this->data, $tmp);
			}
			//remove core?
			if($key === 'core') {
				unset($this->files['core']);
			}
		}
	}

	protected function deepMerge(array $arr1, array $arr2) {
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
					$arr1[$k] = $this->deepMerge($arr1[$k], $v);
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