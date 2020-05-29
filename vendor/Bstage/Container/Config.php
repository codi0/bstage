<?php

//PSR-11 compatible (without interfaces)

namespace Bstage\Container;

class Config {

	protected $data = [];
	protected $dataLocal = [];

	protected $dir = '';
	protected $fileNames = [];
	protected $filePerms = 0640;

	protected $token = '.';
	protected $readonly = false;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if($k === 'data') {
				$this->dataLocal = $v;
			} elseif(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//load data?
		if($this->dir) {
			$this->load();
		}
	}

	public function has($id) {
		return $this->get($id) !== null;
	}

	public function get($id, $default=null) {
		//set vars
		$idArr = $id ? explode($this->token, $id) : [];
		$idCount = count($idArr) - 1;
		//check local and saved data
		foreach([ $this->dataLocal, $this->data ] as $i1 => $data) {
			//loop through segments
			foreach($idArr as $i2 => $segment) {
				//set vars
				$res = '%%null%%';
				//is array like?
				if(is_array($data) || $data instanceof \ArrayAccess) {
					if(array_key_exists($segment, $data)) {
						$res = $data[$segment];
					}
				} else if(is_object($data)) {
					//call method?
					if(substr($segment, -2) === '()') {
						$segment = substr($segment, 0, -2);
						if(is_callable([ $data, $segment ])) {
							$res = $data->$segment();
						}
					} else {
						if(isset($data->$segment)) {
							$res = $data->$segment;
						}
					}
				}
				//not found?
				if($res === '%%null%%') {
					if($i1 == 1) {
						return $default;
					} else {
						break;
					}
				}
				//complete?
				if($idCount == $i2) {
					return $res;
				} else {
					$data = $res;
				}
			}	
		}
		//not found
		return $default;
	}

	public function set($id, $value, $save=true) {
		//read only?
		if($this->readonly) {
			throw new \Exception('Data is read only');
		}
		//set vars
		$arr = (!$id && $value) ? $value : [];
		$tmp =& $arr;
		$idArr = $id ? explode($this->token, $id) : [];
		$source = $save ? $this->data : $this->dataLocal;
		//valid input?
		if(!$idArr && !is_array($value)) {
			throw new \Exception('Value must be an array, if no key set');
		}
		//loop through segments
		foreach($idArr as $index => $seg) {
			if($index+1 < count($idArr)) {
				$tmp[$seg] = [];
				$tmp =& $tmp[$seg];
			} else {
				$tmp[$seg] = $value;
			}
		}
		//merge data?
		if(!empty($arr)) {
			$data = $this->arrayMergeRecursive($source, $arr);
		} else {
			$data = $source;
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
		if($this->data && $this->dataLocal) {
			return $this->arrayMergeRecursive($this->data, $this->dataLocal);
		} else {
			return (array) ($this->data || $this->dataLocal);
		}
	}

	public function save() {
		//read only?
		if($this->readonly) {
			throw new \Exception('Data is read only');
		}
		//anytihng to save?
		if(!$this->dir) return;
		//set vars
		$coreData = $this->data;
		$coreFile = $this->dir . '/core.php';
		//loop through files
		foreach($this->fileNames as $key) {
			//non-core file?
			if(isset($this->data[$key]) && $key !== 'core') {
				//remove from core
				unset($coreData[$key]);
				//build file path
				$file = $this->dir . '/' . $key . '.php';
				$data = [ $key => $this->data[$key] ];
				//save file?
				if(file_put_contents($file, "<?php\n\n return " . var_export($data, true) . ';', LOCK_EX)) {
					$this->filePerms && chmod($file, $this->filePerms);
				}
			}
		}
		//save core file?
		if(file_put_contents($coreFile, "<?php\n\n return " . var_export($coreData, true) . ';', LOCK_EX)) {
			$this->filePerms && chmod($coreFile, $this->filePerms);
		}
		//return
		return true;
	}

	protected function load() {
		//format dir
		$this->dir = rtrim(str_replace('\\', '/', $this->dir), '/');
		//scan for files
		foreach(glob($this->dir . "/*.php") as $file) {
			//load data
			$tmp = include($file);
			//get file name
			$name = basename($file, '.php');
			//add to file names?
			if(!in_array($name, $this->fileNames)) {
				$this->fileNames[] = $name;
			}
			//merge data?
			if(is_array($tmp)) {
				$this->data = array_merge($this->data, $tmp);
			}
		}
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