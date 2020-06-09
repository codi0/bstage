<?php

namespace Bstage\Protocol;

class Opwr {

	protected $crypt;
	protected $events;
	protected $httpClient;

	protected $user = '';
	protected $endpoint = '';
	protected $signKeys = [];
	protected $encryptKeys = [];

	protected $leeway = 30;
	protected $versions = [ 'v1' ];
	protected $allowedCiphers = [ 'aes-256-ctr' ];
	protected $allowedHashes = [ 'sha256' ];

	protected $providers = [ 'opwr' => 'https://api.openwrite.xyz/v1' ];

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function send($endpoint, $action, array $opts=[]) {
		//set opts
		$opts = array_merge([
			'method' => 'GET',
			'headers' => [],
			'body' => '',
			'encrypt' => true, //flag to determine whether to encrypt the request body
			'hash' => $this->allowedHashes[0], //hash algo used for signing and encrypting
			'cipher' => $this->allowedCiphers[0], //cipher used for encrypting
			'from' => $this->user, //identifer for sending user
			'to' => '', //optional identifier for recipient user
			'secret' => '', //optional shared secret to include in signature
			'version' => '', //api version to call
		], $opts);
		//valid hash algorthim?
		if(!in_array($opts['hash'], $this->allowedHashes)) {
			throw new \Exception('Invalid hash algorithm');
		}
		//set vars
		$encryptedBody = '';
		$action = trim($action, '/');
		$endpoint = trim($endpoint, '/');
		//negotiate version?
		if(!$opts['version']) {
			//ask about api
			$response = $this->httpClient->send($endpoint . '/about');
			//get versions
			$versions = (array) ($response->get('body.data.versions') ?: []);
			//check against local versions
			foreach($this->versions as $v) {
				if($v && in_array($v, $versions)) {
					$opts['version'] = $v;
					break;
				}
			}
			//valid endpoint?
			if(!$opts['version']) {
				throw new \Exception('Invalid endpoint');
			}
		}
		//format method
		$opts['method'] = strtoupper($opts['method']);
		//format headers
		$opts['headers'] = $this->formatHeaders($opts['headers'], [
			'x-opwr-from' => 'user=' . $opts['from'] . '; endpoint=' . $this->endpoint . '; version=' . $opts['version'],
			'x-opwr-to' => 'user=' . $opts['to'] . '; endpoint=' . $endpoint . '; action=' . $action,
			'x-opwr-time' => 'timestamp=' . time() . '; nonce=' . $this->crypt->nonce(16),
			'x-opwr-sign' => 'type=rsa; hash=' . $opts['hash'] . '; kid=' . $this->kid($this->signKeys['public']),
		]);
		//format body?
		if(is_array($opts['body'])) {
			//add nonce?
			if($opts['body'] && $opts['encrypt']) {
				$opts['body']['__nonce'] = $this->crypt->nonce(16);
			}
			//urlencoded string
			$opts['body'] = http_build_query($opts['body']);
		}
		//encrypt body?
		if($opts['encrypt'] && $opts['body']) {
			//valid encryption cipher?
			if(!in_array($opts['cipher'], $this->allowedCiphers)) {
				throw new \Exception('Invalid encryption cipher');
			}
			//encryption key found?
			if($encryptKey = $this->key($endpoint, $opts['version'], 'encrypt', $opts['to'])) {
				//encrypt message body
				$encryptedBody = $this->crypt->encryptRsa($opts['body'], $encryptKey, [
					'hash' => $opts['hash'],
					'cipher' => $opts['cipher'],
					'encoding' => 'base64',
				]);
				//success?
				if(!$encryptedBody) {
					throw new \Exception('Encryption failed');
				}
				//add encryption header
				$opts['headers']['x-opwr-encrypt'] = 'type=rsa; hash=' . $opts['hash'] . '; cipher=' . $opts['cipher'];
			}
		}
		//create data to sign
		$data = $opts['method'] . "\n" . $this->signableHeaders($opts['headers']) . "\n" . $opts['body'] . "\n" . $opts['secret'];
		//sign request
		$opts['headers']['x-signature'] = $this->crypt->signRsa($data, $this->signKeys['private'], [
			'hash' => $opts['hash'],
			'encoding' => 'base64',
		]);
		//send data
		return $this->httpClient->send($endpoint . '/' . $opts['version'] . '/' . $action, [
			'method' => $opts['method'],
			'headers' => $opts['headers'],
			'body' => $encryptedBody ?: $opts['body'],
		]);
	}

	public function verify($response, array $opts=[]) {
		//set opts
		$opts = array_merge([
			'secret' => '',
			'checkTo' => true,
			'checkFrom' => true,
			'checkEncrypt' => true,
		], $opts);
		//set vars
		$method = $_SERVER['REQUEST_METHOD'];
		$body = file_get_contents('php://input');
		$headers = $this->formatHeaders($_SERVER);
		$encHeader = isset($headers['x-opwr-encrypt']) ? $headers['x-opwr-encrypt'] : '';
		//check required headers
		foreach([ 'x-signature', 'x-opwr-from', 'x-opwr-to', 'x-opwr-time', 'x-opwr-sign' ] as $k) {
			//is header missing?
			if(!isset($headers[$k]) || !$headers[$k]) {
				$response->fail($k . ': header not found');
				return false;
			}
		}
		//meta data
		$meta = array(
			'from' => $this->parseStr($headers['x-opwr-from'], ';', [ 'user', 'endpoint', 'version', 'provider' ]),
			'to' => $this->parseStr($headers['x-opwr-to'], ';', [ 'user', 'endpoint', 'provider', 'action' ]),
			'time' => $this->parseStr($headers['x-opwr-time'], ';', [ 'timestamp', 'nonce' ]),
			'sign' => $this->parseStr($headers['x-opwr-sign'], ';', [ 'type', 'hash', 'kid' ]),
			'encrypt' => $this->parseStr($encHeader, ';', [ 'type', 'hash', 'cipher' ]),
		);
		//encryption required?
		if($opts['checkEncrypt'] && $body && !$meta['encrypt']['hash']) {
			$response->fail('x-opwr-encypt: message must be encrypted');
			return false;
		}
		//valid timestamp?
		if($meta['time']['timestamp'] < time()-$this->leeway || $meta['time']['timestamp'] > time()+$this->leeway) {
			$response->fail('x-opwr-time: invalid timestamp');
			return false;
		}
		//valid from user?
		if($opts['checkFrom'] && !$meta['from']['user']) {
			$response->fail('x-opwr-from: invalid user');
			return false;
		}
		//valid from endpoint?
		if(!filter_var($meta['from']['endpoint'], FILTER_VALIDATE_URL)) {
			$response->fail('x-opwr-from: invalid endpoint');
			return false;
		}
		//valid version?
		if(!$meta['from']['verison'] || !in_array($meta['from']['version'], $this->versions)) {
			$response->fail('x-opwr-from: unsupported api version');
			return false;
		}
		//valid to user?
		if($opts['checkTo'] && !$meta['to']['user']) {
			$response->fail('x-opwr-to: invalid user');
			return false;
		}
		//valid to endpoint?
		if(str_replace('http://', 'https://', $meta['to']['endpoint']) !== str_replace('http://', 'https://', $this->endpoint)) {
			$response->fail('x-opwr-to: invalid endpoint');
			return false;
		}
		//valid hash algo sent?
		if(!in_array($meta['sign']['hash'], $this->allowedHashes)) {
			$response->fail('x-opwr-sign: invalid hash algorithm');
			return false;
		}
		//valid kid sent?
		if(!$meta['sign']['kid']) {
			$response->fail('x-opwr-sign: invalid signature key id');
			return false;
		}
		//check for provider match
		foreach($this->providers as $k => $v) {
			//from match?
			if($k && strpos($meta['from']['user'], $k . '!') === 0) {
				$meta['from']['provider'] = $v;
			}
			//to match?
			if($k && strpos($meta['to']['user'], $k . '!') === 0) {
				$meta['to']['provider'] = $v;
			}
		}
		//check from provider?
		if($meta['from']['provider']) {
			//make request to provider
			$result = $this->httpClient->send($meta['from']['provider'] . '/users/' . $meta['from']['user'] . '/endpoints');
			//get endpoints
			$endpoints = $result->get('body.data.endpoints', []);
			//endpoint match found?
			if(!in_array($meta['from']['endpoint'], $endpoints)) {
				$response->fail('User rejected as invalid by provider ' . parse_url($meta['from']['provider'], PHP_URL_HOST));
				return false;
			}
		}
		//EVENT: opwr.protocol.verify
		$event = $this->events->dispatch('opwr.protocol.verify', [
			'data' => $meta,
			'response' => $response,
		]);
		//get updated values
		$meta = $event->data ?: $meta;
		$response = $event->response ?: $response;
		//stop here?
		if(!$response->isOk()) {
			return false;
		}
		//has shared secret?
		if(isset($meta['secret']) && is_string($meta['secret'])) {
			$opts['secret'] = $meta['secret'];
		}
		//decrypt data?
		if($meta['encrypt']['hash'] && $body) {
			//valid hash algo sent?
			if(!in_array($meta['encrypt']['hash'], $this->allowedHashes)) {
				$response->fail('x-opwr-encrypt: invalid hash algorithm');
				return false;
			}
			//valid cipher sent?
			if(!in_array($meta['encrypt']['cipher'], $this->allowedCiphers)) {
				$response->fail('x-opwr-encrypt: invalid cipher');
				return false;
			}
			//decrypt message body
			$decrypted = $this->crypt->decryptRsa($body, $this->encryptKeys['private'], [
				'hash' => $meta['encrypt']['hash'],
				'cipher' => $meta['encrypt']['cipher'],
				'encoding' => 'base64',
			]);
			//is decrypted?
			if(!$decrypted) {
				$response->fail('Message decryption failed');
				return false;
			}
			//update body
			$body = $decrypted;
			//update $_POST
			$_POST = $this->parseStr($body);
			//remove nonce?
			if(isset($_POST['__nonce'])) {
				unset($_POST['__nonce']);
			}
		}
		//get signature verification key?
		if(!$signKey = $this->key($meta['from']['endpoint'], $meta['from']['version'], 'sign', $meta['from']['user'], $meta['sign']['kid'])) {
			$response->fail('Failed to retrieve public key from ' . parse_url($meta['from']['endpoint'], PHP_URL_HOST));
			return false;
		}
		//build signature data
		$data = $method . "\n" . $this->signableHeaders($headers) . "\n" . $body . "\n" . $opts['secret'];
		//verify signature
		$verify = $this->crypt->verifyRsa($data, $headers['x-signature'], $signKey, [
			'hash' => $meta['sign']['hash'],
			'encoding' => 'base64',
		]);
		//valid signature?
		if(!$verify) {
			$response->fail('Signature verification failed');
			return false;
		}
		//success
		return array( 'headers' => $headers, 'body' => $_POST, 'meta' => $meta );
	}

	public function discover($user, $endpoint) {
		//set vars
		$provider = '';
		//check providers
		foreach($this->providers as $k => $v) {
			//match found?
			if($k && strpos($user, $k . '!') === 0) {
				$provider = $v;
				break;
			}
		}
		//provider found?
		if($provider) {
			//ask provider
			$response = $this->httpClient->send($provider . '/users/' . $user . '/endpoints');
			//has master?
			if(!$master = $response->get('body.data.master')) {
				return false;
			}
			//update endpoint
			$endpoint = $master;
		} else {
			//has endpoint?
			if(!$endpoint) {
				return false;
			}
			//attempt to discover endpoint
			$response = $this->httpClient->send($endpoint . '?opwr=xyz');
			//valid response?
			if(!$response->get('body.data.id') !== 'opwr.client') {
				return false;
			}
			//get response data
			$endpoint = trim($response->get('body.data.endpoint'), '/');
		}
		//return
		return filter_var($endpoint, FILTER_VALIDATE_URL) ? $endpoint : false;
	}

	public function key($endpoint, $version, $action, $user=null, $kid=null) {
		//build url
		$url = $endpoint . '/' . $version . '/keys' . ($user ? '/' . $user : '');
		//make request
		$response = $this->httpClient->send($url);
		//keys found?
		if(!$keys = $response->get('body.data')) {
			return null;
		}
		//loop through keys
		foreach($keys as $k => $v) {
			//valid use?
			if(!isset($v['use']) || $v['use'] !== $action) {
				continue;
			}
			//valid kid?
			if($kid && $k !== $kid) {
				continue;
			}
			//return key?
			if(isset($v['key']) && $v['key']) {
				return $v['key'];
			}
		}
		//not found
		return null;
	}

	public function kid($key) {
		//create hash?
		if(strlen($key) != 32) {
			$key = md5($key);
		}
		//return
		return $key;
	}

	public function keysEndpoint() {
		return [
			$this->kid($this->signKeys['public']) => [
				'use' => 'sign',
				'key' => $this->signKeys['public'],
			],
			$this->kid($this->encryptKeys['public']) => [
				'use' => 'encrypt',
				'key' => $this->encryptKeys['public'],
			],
		];
	}

	protected function formatHeaders($headers, array $additional=[]) {
		//to array?
		if(!is_array($headers)) {
			$headers = explode("\r\n", $headers);
		}
		//is server?
		$isServer = isset($headers['REQUEST_METHOD']);
		//loop through array
		foreach($headers as $name => $value) {
			//remove from array
			unset($headers[$name]);
			//skip header?
			if($isServer && strpos($name, 'HTTP_') !== 0) {
				continue;
			}
			//parse header?
			if(is_numeric($name)) {
				$exp = explode(':', $name, 2);
				$name = trim($exp[0]);
				$value = isset($exp[1]) ? trim($exp[1]) : '';
			}
			//format name
			$name = strtolower($name);
			$name = str_replace('_', '-', $name);
			$name = preg_replace('/^http-/', '', $name);
			//add to array?
			if($name && $value) {
				$headers[$name] = $value;
			}
		}
		//additional headers
		foreach($additional as $k => $v) {
			$headers[$k] = $v;
		}
		//return
		return $headers;
	}

	protected function signableHeaders(array $headers) {
		//format headers?
		if(isset($headers['REQUEST_METHOD'])) {
			$headers = $this->formatHeaders($headers);
		}
		//loop through headers
		foreach($headers as $name => $value) {
			//remove from array
			unset($headers[$name]);
			//add to array?
			if(strpos($name, 'x-opwr-') === 0) {
				$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
				$headers[$name] = $name . ': ' . $value;
			}
		}
		//sort
		ksort($headers);
		//return
		return implode("\r\n", $headers);
	}

	protected function parseStr($body, $token='&', array $defaultKeys=[]) {
		//set vars
		$params = [];
		//can parse?
		if($body && is_string($body)) {
			//check first key
			$exp = explode('=', $body);
			//is valid key?
			if(isset($exp[1]) && strlen($exp[0]) < 30 && strpos($exp[0], ' ') === false) {
				//replace token
				$body = str_replace($token, '&', $body);
				//parse string
				parse_str($body, $test);
				//update params?
				if($test) $params = $test;
			}
		}
		//check for default keys
		foreach($defaultKeys as $key) {
			if(!isset($params[$key])) {
				$params[$key] = null;
			}
		}
		//return
		return $params;
	}

}