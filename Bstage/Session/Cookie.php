<?php

namespace Bstage\Session;

class Cookie extends AbstractSession {

	protected $cookie = null;

	protected $data = array();
	protected $started = false;
	protected $changed = false;

	public function __destruct() {
		$this->close();
	}

	public function id() {
		return isset($_COOKIE[$this->name]) ? md5($_COOKIE[$this->name]) : null;
	}

	public function name() {
		return $this->name;
	}

	public function start() {
		//is started?
		if($this->started) {
			return true;
		}
		//set started flag
		$this->started = true;
		//get cookie data
		$data = $this->cookie->get($this->name, [
			'signed' => true,
			'encrypted' => true,
		]);
		//is valid?
		if(is_array($data)) {
			$this->data = $data;
		}
		//return
		return true;
	}

	public function close() {
		//has started?
		if(!$this->started) {
			return true;
		}
		//reset started flag
		$this->started = false;
		//has changed?
		if(!$this->changed) {
			return true;
		}
		//update cookie
		$this->cookie->set($this->name, $this->data, [
			'expires' => $this->lifetime,
			'path' => $this->path,
			'domain' => $this->domain,
			'secure' => $this->secure,
			'httponly' => $this->httponly,
			'sign' => true,
			'encrypt' => true,
		]);
		//reset changed flag
		$this->changed = false;
		//return
		return true;
	}

	public function get($key, $default=null) {
		//start session
		$this->start();
		//get property
		return isset($this->data[$key]) ? $this->data[$key] : $default;
	}

	public function set($key, $val) {
		//start session
		$this->start();
		//set changed flag
		$this->changed = true;
		//set property
		$this->data[$key] = $val;
		//return
		return true;
	}

	public function delete($key) {
		//start session
		$this->start();
		//remove key?
		if(array_key_exists($key, $this->data)) {
			//set changed flag
			$this->changed = true;
			//delete key
			unset($this->data[$key]);
		}
		//return
		return true;
	}

	public function flush() {
		//start session
		$this->start();
		//set changed flag
		$this->changed = true;
		//clear data
		$this->data = array();
		//return
		return true;
	}

	public function gc() {
		return true;
	}

	public function destroy() {
		//start session
		$this->start();
		//clear data
		$this->data = array();
		//session closed
		$this->started = false;
		//delete cookie
		return $this->cookie->delete($this->name, array(
			'path' => $this->path,
			'domain' => $this->domain,
			'secure' => $this->secure,
			'httponly' => $this->httponly,		
		));
	}

	public function regenerate() {
		return true;	
	}

}