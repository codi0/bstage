<?php

namespace Bstage\Http\Session;

class Cookie extends File {

	protected $cookie = null;
	protected $opened = false;

	public function id() {
		return isset($_COOKIE[$this->name]) ? md5($_COOKIE[$this->name]) : null;
	}

	public function name() {
		return $this->name;
	}

	public function open() {
		//is started?
		if($this->opened) {
			return true;
		}
		//set started flag
		$this->opened = true;
		//get cookie data
		$data = $this->cookie->get($this->name, [
			'signed' => true,
			'encrypted' => true,
		]);
		//set data
		$_SESSION = is_array($data) ? $data : [];
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
		//has changed?
		if(!$this->changed) {
			return true;
		}
		//update cookie
		$this->cookie->set($this->name, $_SESSION, [
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

	public function gc() {
		return true;
	}

	public function destroy() {
		//start session
		$this->open();
		//clear data
		$_SESSION = array();
		//session closed
		$this->opened = false;
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