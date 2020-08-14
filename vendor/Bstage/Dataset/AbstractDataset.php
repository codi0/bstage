<?php

namespace Bstage\Dataset;

abstract class AbstractDataset {

	protected $data = [];

	public function getAll($prepend=[], $byKey=true) {
		//set vars
		$tmp = [];
		$data = $this->data;
		//prepend data
		foreach($prepend as $k => $v) {
			if($byKey) {
				//by key
				if(array_key_exists($v, $data)) {
					$tmp[$v] = $data[$v];
					unset($data[$v]);
				}
			} else {
				//by val
				if(array_key_exists($k, $data)) {
					unset($data[$k]);
				}
				$tmp[$k] = $v;
			}
		}
		//return
		return $tmp + $data;
	}

	public function getByKey($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

}