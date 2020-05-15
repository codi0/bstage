<?php

namespace Bstage\Http;

class Cookie {

	protected $signKey = '';
	protected $signToken = '.';
	protected $signHash = 'sha1';

	protected $encryptKey = '';

	protected $crypt = null;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//crypt set?
		if(!$this->crypt) {
			throw new \Exception("Crypt object not set");
		}
		//valid sign key?
		if(strlen($this->signKey) < 16) {
			throw new \Exception("Sign key must be at least 16 bytes");
		}
		//valid encrypt key?
		if(strlen($this->encryptKey) < 16) {
			throw new \Exception("Encrypt key must be at least 16 bytes");
		}
	}

	public function get($name, array $opts=array()) {
		//set opts
		$opts = array_merge(array(
			'signed' => false,
			'encrypted' => false,
			'default' => null,
		), $opts);
		//cookie found?
		if(!isset($_COOKIE[$name]) || !$_COOKIE[$name]) {
			return $opts['default'];
		}
		//set data
		$data = $_COOKIE[$name];
		//is encrypted?
		if($opts['encrypted']) {
			$data = $this->crypt->decrypt($data, $this->encryptKey);
		}
		//is signed?
		if($opts['signed']) {
			//split hash
			$segments = explode($this->signToken, $data, 2);
			$hash = $segments[0];
			$data = isset($segments[1]) ? $segments[1] : null;
			//verify hash?
			if($data === null || $hash !== $this->calcSignature($data)) {
				//delete cookie
				$this->delete($name);
				//return
				return $opts['default'];
			}
		}
		//decode data?
		if(($test = json_decode($data, true)) !== null) {
			$data = $test;
		}
		//return
		return $data;
	}

	public function set($name, $data, array $opts=array()) {
		//too late?
		if(headers_sent()) {
			return false;
		}
		//set opts
		$opts = array_merge(array(
			'expires' => 0,
			'path' => '/',
			'domain' => '',
			'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? $_SERVER['HTTPS'] !== 'off' : $_SERVER['SERVER_PORT'] === 443,
			'httponly' => true,
			'sign' => false,
			'encrypt' => false,
		), $opts);
		//set time?
		if($opts['expires'] > 0) {
			$opts['expires'] = time() + $opts['expires'];
		} elseif($opts['expires'] < 0) {
			$opts['expires'] = 1;
		}
		//encode data?
		if(!is_string($data) && !is_numeric($data)) {
			$data = json_encode($data);
		}
		//sign data?
		if($opts['sign'] && $opts['expires'] != 1) {
			$data = $this->calcSignature($data) . $this->signToken . $data;
		}
		//encrypt data?
		if($opts['encrypt'] && $opts['expires'] != 1) {
			$data = $this->crypt->encrypt($data, $this->encryptKey);
		}
		//set cookie
		return setcookie($name, $data, $opts['expires'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']);
	}

	public function delete($name, array $opts=array()) {
		//delete global?
		if(isset($_COOKIE[$name])) {
			unset($_COOKIE[$name]);
		}
		//unset cookie
		return $this->set($name, '', array_merge($opts, array(
			'expires' => -1,
		)));
	}

	protected function calcSignature($data) {
		return $this->crypt->sign($data, $this->signKey, array( 'hash' => $this->signHash ));
	}

}