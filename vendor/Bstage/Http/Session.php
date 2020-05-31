<?php

namespace Bstage\Http;

class Session {

	protected $name = 'sess';
	protected $lifetime = 0;
	protected $path = '/';
	protected $domain = '';
	protected $secure = null;
	protected $httponly = true;

	protected $hash = '';
	protected $data = [];
	protected $opened = false;

	protected $cookies;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//is ssl?
		$ssl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? $_SERVER['HTTPS'] !== 'off' : $_SERVER['SERVER_PORT'] === 443;
		//format input
		$this->lifetime = (int) $this->lifetime;
		$this->path = $this->path ?: '/';
		$this->secure = (bool) (is_null($this->secure) ? $ssl : $this->secure);
		$this->httponly = (bool) $this->httponly;
	}

	public function __destruct() {
		$this->close();
	}

	public function clone($name, $lifetime=null) {
		//clone object
		$clone = clone $this;
		//reset vars
		$clone->name = $name;
		$clone->data = [];
		$clone->opened = false;
		//change lifetime?
		if(is_numeric($lifetime)) {
			$clone->lifetime = (int) $lifetime;
		}
		//return
		return $clone;
	}

	public function id() {
		return isset($_COOKIE[$this->name]) ? md5($_COOKIE[$this->name]) : null;
	}

	public function name() {
		return $this->name;
	}

	public function param($key, $val=null) {
		//valid param?
		if(!property_exists($this, $key)) {
			return null;
		}
		//set param?
		if($val !== null) {
			$this->$key = $val;
		}
		//return
		return $this->$key;
	}

	public function open() {
		//is started?
		if($this->opened) {
			return true;
		}
		//set started flag
		$this->opened = true;
		//get cookie data
		$data = $this->cookies->get($this->name, [
			'signed' => true,
			'encrypted' => true,
		]);
		//set data
		$this->data = is_array($data) ? $data : [];
		//store hash
		$this->hash = md5(serialize($this->data));
		//return
		return true;
	}

	public function close() {
		//has started?
		if(!$this->opened) {
			return true;
		}
		//reset started flag
		$this->opened = false;
		//data changed?
		if($this->hash === md5(serialize($this->data))) {
			return true;
		}
		//update cookie
		$this->cookies->set($this->name, $this->data, [
			'expires' => $this->lifetime,
			'path' => $this->path,
			'domain' => $this->domain,
			'secure' => $this->secure,
			'httponly' => $this->httponly,
			'sign' => true,
			'encrypt' => true,
		]);
		//return
		return true;
	}

	public function get($key, $default=null) {
		//start session
		$this->open();
		//return value
		return isset($this->data[$key]) ? $this->data[$key] : $default;	
	}

	public function set($key, $val) {
		//start session
		$this->open();
		//set value
		$this->data[$key] = $val;
		//return
		return true;
	}

	public function delete($key) {
		//start session
		$this->open();
		//delte value?
		if(array_key_exists($key, $this->data)) {
			unset($this->data[$key]);
		}
		//return
		return true;
	}

	public function flush() {
		//start session
		$this->open();
		//reset global
		$this->data = [];
		//return
		return true;
	}

	public function destroy() {
		//start session
		$this->open();
		//clear data
		$this->data = [];
		//session closed
		$this->opened = false;
		//delete cookie
		return $this->cookies->delete($this->name, [
			'path' => $this->path,
			'domain' => $this->domain,
			'secure' => $this->secure,
			'httponly' => $this->httponly,		
		]);
	}

	public function gc() {
		return true;
	}

	public function regenerate() {
		return true;	
	}

}