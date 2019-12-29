<?php

namespace Bstage\Session;

abstract class AbstractSession {

	protected $name = 'sessdata';
	protected $lifetime = 0;
	protected $path = '/';
	protected $domain = '';
	protected $secure = null;
	protected $httponly = true;

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

	abstract public function id();

	abstract public function name();

	abstract public function start();

	abstract public function close();

	abstract public function get($key, $default=null);

	abstract public function set($key, $val);

	abstract public function delete($key);

	abstract public function flush();

	abstract public function gc();

	abstract public function destroy();

	abstract public function regenerate();

}