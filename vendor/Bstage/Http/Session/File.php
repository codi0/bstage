<?php

namespace Bstage\Http\Session;

class File {

	protected $name = 'sess';
	protected $lifetime = 0;
	protected $path = '/';
	protected $domain = '';
	protected $secure = null;
	protected $httponly = true;

	protected $gcDivisor = 100;
	protected $changed = false;
	protected $handler = null;

	public function __construct(array $opts=array()) {
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

	public function id() {
		//start session
		$this->open();
		//return ID
		return session_id();
	}

	public function name() {
		//start session
		$this->open();
		//return name
		return session_name();
	}

	public function open() {
		//has started?
		if(session_status() === PHP_SESSION_ACTIVE) {
			return true;
		}
		//set ini params
		@ini_set('session.gc_probability', $this->gcDivisor ? 1 : 0);
		@ini_set('session.gc_divisor', $this->gcDivisor ?: 100);
		@ini_set('session.gc_maxlifetime', $this->lifetime ?: 1800);
		@ini_set('session.cookie_lifetime', $this->lifetime);
		//set cookie params
		session_set_cookie_params($this->lifetime, $this->path, $this->domain, $this->secure, $this->httponly);
		//set name?
		if($this->name) {
			session_name($this->name);
		}
		//set handler?
		if($this->handler) {
			session_set_save_handler($this->handler, true);
		}
		//start session
		$ret = session_start();
		//old session?
		if(isset($_SESSION['__OLD']) && $_SESSION['__OLD'] < time()) {
			//restart session
			$_SESSION = array();
			session_destroy();
			$ret = session_start();
		} elseif(mt_rand(1, 1000) <= 50) {
			//5% chance of regen
			$this->regen();
		}
		//check timeout?
		if($this->lifetime > 0) {
			//has session expired?
			if(isset($_SESSION['__LAST']) && ($_SESSION['__LAST'] + $this->lifetime) < time()) {
				//restart session
				$_SESSION = array();
				session_destroy();
				$ret = session_start();
			}
			//update timestamp
			$_SESSION['__LAST'] = time();
			//update session cookie
			setcookie(session_name(), session_id(), time()+$this->lifetime, $this->path, $this->domain, $this->secure, $this->httponly); 		
		}
		//return
		return $ret;
	}

	public function close() {
		//anything to close?
		if(session_status() !== PHP_SESSION_ACTIVE) {
			return true;
		}
		//close session
		return session_write_close();
	}

	public function get($key, $default=null) {
		//start session
		$this->open();
		//return value
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;	
	}

	public function set($key, $val) {
		//start session
		$this->open();
		//mark as changed
		$this->changed = true;
		//set value
		$_SESSION[$key] = $val;
		//return
		return true;
	}

	public function delete($key) {
		//start session
		$this->open();
		//anything to delete?
		if(array_key_exists($key, $_SESSION)) {
			//mark as changed
			$this->changed = true;
			//remove value
			unset($_SESSION[$key]);
		}
		//return
		return true;
	}

	public function flush() {
		//start session
		$this->open();
		//reset global
		$_SESSION = array();
		//mark as changed
		$this->changed = true;
		//return
		return true;
	}

	public function gc() {
		//force gc
		$this->gcDivisor = 1;
		//start session
		$this->open();
		//close session
		$this->close();
		//start session
		return $this->open();	
	}

	public function destroy() {
		//start session
		$this->open();
		//get session name
		$name = session_name();
		//reset global
		$_SESSION = array();
		//destroy session
		$ret = session_destroy();
		//delete cookie
		setCookie($name, '', 1, $this->path, $this->domain, $this->secure, $this->httponly);
		//return
		return $ret;	
	}

	public function regenerate() {
		//start session
		$this->open();
		//old session?
		if(isset($_SESSION['__OLD']) && $_SESSION['__OLD'] > 0) {
			return false;
		}
		//mark as old
		$_SESSION['__OLD'] = time() + 10;
		//generate new ID
		session_regenerate_id(false);
		//get new ID
		$newId = session_id();
		//close session
		session_write_close();
		//start new session
		session_id($newId);
		session_start();
		//remove old data?
		if(isset($_SESSION['__OLD'])) {
			unset($_SESSION['__OLD']);
		}
		//return
		return true;
	}

}