<?php

namespace Bstage\Http\Session;

class Cookie {

	protected $name = 'sess';
	protected $lifetime = 0;
	protected $path = '/';
	protected $domain = '';
	protected $secure = null;
	protected $httponly = true;
	protected $refresh = 0;

	protected $data = [];
	protected $hash = '';
	protected $opened = false;

	protected $cookies;
	protected $factory = [];

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//check for ssl?
		if($this->secure === null) {
			$this->secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? $_SERVER['HTTPS'] !== 'off' : $_SERVER['SERVER_PORT'] === 443;
		}
		//format input
		$this->path = $this->path ?: '/';
		$this->secure = ($this->secure !== false);
		$this->httponly = (bool) $this->httponly;
		$this->lifetime = (int) $this->lifetime;
		//add to factory
		$this->factory[$this->name] = $this;
		//close before headers sent
		header_register_callback([ $this, 'close' ]);
	}

	public function factory($name, array $opts=[]) {
		//is cached?
		if(!isset($this->factory[$name])) {
			//create object
			$this->factory[$name] = new self(array_merge([
				'name' => $name,
				'lifetime' => $this->lifetime,
				'path' => $this->path,
				'domain' => $this->domain,
				'secure' => $this->secure,
				'httponly' => $this->httponly,
				'refresh' => $this->refresh,
				'cookies' => $this->cookies,
			], $opts));
		}
		//return
		return $this->factory[$name];
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
			//restart session?
			if(in_array($key, [ 'name' ]) && $this->opened) {
				if($this->$key !== $val) {
					$this->open(true);
				}
			}
			//update value
			$this->$key = $val;
		}
		//return
		return $this->$key;
	}

	public function open($force=false) {
		//is started?
		if($this->opened && !$force) {
			return true;
		}
		//set started flag
		$this->opened = true;
		//get cookie data
		$data = $this->cookies->get($this->name, [
			'signed' => true,
			'encrypted' => true,
		]);
		//set session data
		$this->data = is_array($data) ? $data : [];
		$this->hash = md5(serialize($this->data));
		//return
		return true;
	}

	public function close() {
		//has started?
		if(!$this->opened) {
			return true;
		}
		//refresh session?
		if($this->data && $this->lifetime && $this->refresh) {
			if($this->get('__t', 0) < (time() - $this->refresh)) {
				$this->set('__t', time());
			}
		}
		//reset started flag
		$this->opened = false;
		//data changed?
		if($this->hash === md5(serialize($this->data))) {
			return true;
		}
		//update cookie
		$res = $this->cookies->set($this->name, $this->data, [
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