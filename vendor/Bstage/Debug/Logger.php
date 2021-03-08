<?php

//PSR-3 compatible (without interfaces)

namespace Bstage\Debug;

class Logger {

	protected $filePath = '';
	protected $display = false;
	protected $levels = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' );

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//valid file path?
		if(!$this->filePath) {
			throw new \Exception("Invalid log file path");
		}
		//get file dir
		$dir = dirname($this->filePath);
		//create dir?
		if($dir[0] === '/' && !is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
	}

	public function log($level, $message, array $context = array()) {
		//valid level?
		if(!in_array($level, $this->levels)) {
			throw new \Exception("Invalid log level: $level");
		}
        //format log data
        $message = $this->interpolate($message, $context);
		$data = json_encode($context, JSON_UNESCAPED_SLASHES) ?: '{}';
		//build log line
		$logLine = array();
		$logLine[] = '[' . date('Y-m-d H:i:s') . ']';
		$logLine[] = '[' . $level . ']';
		$logLine[] = str_replace(array( "\r\n", "\n" ), ' ', trim($message));
		$logLine[] = str_replace(array( "\r\n", "\n" ), ' ', trim($data));
		//convert to string
		$logLine = implode('  ', $logLine);
		//log to file?
		if(file_put_Contents($this->filePath, $logLine . "\n", LOCK_EX|FILE_APPEND) === false) {
			throw new \Exception("Writing to log file failed");
		}
        //display log?
        if($this->display) {
			print $logLine;
        }
	}

	public function emergency($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function alert($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function critical($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function error($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function warning($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function notice($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function info($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	public function debug($message, array $context = array()) {
		return $this->log(__FUNCTION__, $message, $context);
	}

	protected function interpolate($message, array $context = array()) {
		//set vars
		$replace = array();
		//loop through context data
		foreach($context as $key => $val) {
			//value must be scalar
			if(!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
				$replace['{' . $key . '}'] = $val;
			}
		}
		//replace placeholders
		return strtr($message, $replace);
	}

}