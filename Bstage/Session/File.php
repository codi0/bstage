<?php

namespace Bstage\Session;

class File extends AbstractSession {

	protected $gcDivisor = 100;

	public function id() {
		//start session
		$this->start();
		//return ID
		return session_id();
	}

	public function name() {
		//start session
		$this->start();
		//return name
		return session_name();
	}

	public function start() {
		//has started?
		if(session_status() === PHP_SESSION_ACTIVE) {
			return true;
		}
		//disable gc?
		if(session_save_path() && !is_readable(session_save_path())) {
			$this->gcDivisor = 0;
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
		//has started?
		if(session_status() !== PHP_SESSION_ACTIVE) {
			return true;
		}
		//return
		return session_write_close();
	}

	public function get($key, $default=null) {
		//start session
		$this->start();
		//get property
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
	}

	public function set($key, $val) {
		//start session
		$this->start();
		//set property
		$_SESSION[$key] = $val;
		//return
		return true;
	}

	public function delete($key) {
		//start session
		$this->start();
		//key exists?
		if(array_key_exists($key, $_SESSION)) {
			unset($_SESSION[$key]);
		}
		//return
		return true;
	}

	public function flush() {
		//start session
		$this->start();
		//reset global
		$_SESSION = array();
		//return
		return true;
	}

	public function gc() {
		//force gc
		$this->gcDivisor = 1;
		//close session
		$this->close();
		//start session
		return $this->start();
	}

	public function destroy() {
		//start session
		$this->start();
		//get session name
		$name = session_name();
		//reset global
		$_SESSION = array();
		//destroy session
		$ret = session_destroy();
		//delete cookie
		setCookie($name, '', 1);
		//return
		return $ret;
	}

	public function regenerate() {
		//start session
		$this->start();
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