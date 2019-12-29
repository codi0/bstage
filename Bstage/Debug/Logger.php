<?php

//PSR-3 compatible (without interfaces)

namespace Bstage\Debug;

class Logger {

	protected $file = '';
	protected $display = false;
	
	private static $_levels = array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' );
	private static $_factory = array();

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//get file name
		$name = pathinfo($this->file, PATHINFO_FILENAME);
		//valid file path?
		if(!$name || !$this->file) {
			throw new \Exception("Invalid log file set");
		}
		//add to factory cache
		self::$_factory[$name] = $this;
	}

	public function __get($name) {
		return $this->create($name);
	}

	public function create($name, array $opts=array()) {
		//create object?
		if(isset(self::$_factory[$name])) {
			return self::$_factory[$name];
		}
		//set vars
		$class = get_class($this);
		$dir = dirname($this->file);
		$file = $dir . '/' . $name . '.log';
		//default opts
		$defOpts = array( 'file' => $file, 'display' => $this->display );
		//create class
		return new $class(array_merge($defOpts, $opts));
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

	public function log($level, $message, array $context = array()) {
		//valid level?
		if(!in_array($level, self::$_levels)) {
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
		if(file_put_Contents($this->file, $logLine . "\n", LOCK_EX|FILE_APPEND) === false) {
			throw new \Exception("Writing to log file failed");
		}
        //display log?
        if($this->display) {
			print $logLine;
        }
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