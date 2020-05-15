<?php

namespace Bstage\View;

class Jsend {

	protected $status;
	protected $code;
	protected $message;
	protected $data;
	protected $override;

	protected $events;

	public function __construct(array $opts=[]) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	public function __toString() {
		return $this->render([ 'string' => true ]);
	}

	public function isOk() {
		return !in_array($this->status, array( 'error', 'fail' ));
	}

	public function success($message=null, $code=null) {
		//set properties
		$this->status = 'success';
		$this->message = $message;
		$this->code = $code;
		//chain it
		return $this;
	}

	public function fail($message, $code=null) {
		//set properties
		$this->status = 'fail';
		$this->message = $message;
		$this->code = $code;
		//chain it
		return $this;
	}

	public function error($message, $code=null) {
		//set properties
		$this->status = 'error';
		$this->message = $message;
		$this->code = $code;
		//chain it
		return $this;
	}

	public function data($data, $replace=true) {
		//reset to array?
		if(!is_array($data) || !$replace) {
			$this->data = is_array($this->data) ? $this->data : array();
		}
		//save data
		if(is_array($data)) {
			$this->data = $replace ? $data : array_merge($this->data, $data);
		} else {
			$this->data[$data] = $replace;
		}
		//chain it
		return $this;
	}

	public function override($data) {
		//set property
		$this->status = 'success';
		$this->override = $data;
		//chain it
		return $this;
	}

	public function render(array $opts=[]) {
		//format opts
		$opts = array_merge([
			'string' => false,
			'headers' => true,
		], $opts);
		//use default render?
		if(!($jsend = $this->override)) {
			//set status?
			if(!$this->status) {
				$this->status = $this->data ? 'success' : 'fail';
			}
			//set message?
			if(!$this->message) {
				$this->message = ($this->status === 'success') ? null : 'Invalid call';
			}
			//jsend array
			$jsend = array(
				'status' => $this->status,
				'message' => $this->message,
				'code' => $this->code,
				'data' => $this->data,
			);
			//jsend event?
			if($this->events) {
				//EVENT: jsend.output
				$event = $this->events->dispatch('jsend.output', $jsend);
				//update jsend
				$jsend = $event->getParams();
			}
		}
		//convert to json
		$jsend = json_encode($jsend, JSON_PRETTY_PRINT);
		//set headers?
		if($opts['headers']) {
			header('Content-Type: application/json; charset=utf-8');
			header('X-Robots-Tag: noindex,nofollow');
		}
		//return string?
		if($opts['string']) {
			return $jsend;
		}
		//echo output
		echo $jsend;
	}

}