<?php

namespace Bstage\Debug;

class UnitTest {

	public $data = array();

	protected $tests = array();
	protected $results = array();
	protected $setupCbs = array();
	protected $teardownCbs = array();

	protected $currentTest = '';
	protected $title = '';

	protected $logger = null;

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//get title from sub-class?
		if(!$this->title && get_class($this) !== __CLASS__) {
			$ref = new \ReflectionClass($this);
			$this->title = $ref->getShortName();
		}
	}

	public function getTitle() {
		return $this->title;
	}

	public function getResults() {
		return $this->results;
	}

	public function setLogger($logger) {
		//set property
		$this->logger = $logger;
		//chain it
		return $this;
	}

	public function addTest($name, $callback=null) {
		//has callback?
		if(is_callable($name)) {
			$callback = $name;
			$name = "Test #" . (count($this->tests) + 1);
		}
		//valid callback?
		if(!is_callable($callback)) {
			throw new \Exception("Invalid test callback");
		}
		//add to array
		$this->tests[$name] = $callback;
		//chain it
		return $this;
	}

	public function addSetup($callback) {
		//valid callback?
		if(!is_callable($callback)) {
			throw new \Exception("Invalid test callback");
		}
		//add to array
		$this->setupCbs[] = $callback;
		//chain it
		return $this;	
	}

	public function addTeardown($callback) {
		//valid callback?
		if(!is_callable($callback)) {
			throw new \Exception("Invalid test callback");
		}
		//add to array
		$this->teardownCbs[] = $callback;
		//chain it
		return $this;	
	}

    /**
     * {@inheritdoc}
     */
	public function run() {
		//check for 'test' methods
		foreach(get_class_methods($this) as $method) {
			//is test method?
			if(strpos($method, 'test') === 0) {
				//get test name
				$name = substr($method, 4);
				//add test case
				$this->add($name, array( $this, $method ));
			}
		}
		//run setup method?
		if(method_exists($this, 'setup')) {
			$this->setup();
		}
		//run setup callbacks
		foreach($this->setupCbs as $setup) {
			call_user_func($setup, $this);
		}
		//run tests
		foreach($this->tests as $name => $test) {
			//cache name
			$this->currentTest = $name;
			//run test
			call_user_func($test, $this);
		}
		//run teardown callbacks
		foreach($this->teardownCbs as $teardown) {
			call_user_func($teardown, $this);
		}
		//run teardown method?
		if(method_exists($this, 'teardown')) {
			$this->teardown();
		}
		//return result
		return $this->results;
	}

	public function pass($message='') {
		return $this->logResult(true, $message);
	}

	public function fail($message='') {
		return $this->logResult(false, $message);
	}

	public function assertTrue($arg, $message='') {
		return $this->logResult($arg == true, $message);
	}

	public function assertFalse($arg, $message='') {
		return $this->logResult($arg == false, $message);
	}

	public function assertEquals($arg1, $arg2, $message='') {
		return $this->logResult($arg1 == $arg2, $message);
	}

	public function assertNotEquals($arg1, $arg2, $message='') {
		return $this->logResult($arg1 != $arg2, $message);
	}

	public function assertSame($arg1, $arg2, $message='') {
		return $this->logResult($arg1 === $arg2, $message);
	}

	public function assertNotSame($arg1, $arg2, $message='') {
		return $this->logResult($arg1 !== $arg2, $message);
	}

	public function assertInArray($arg, array $arr, $message='') {
		return $this->logResult(in_array($arg, $arr), $message);
	}

	public function assertNotInArray($arg, array $arr, $message='') {
		return $this->logResult(!in_array($arg, $arr), $message);
	}

	protected function logResult($result, $message='') {
		//current test set?
		if(!$this->currentTest) {
			throw new \Exception("Current test name not set");
		}
		//test results exist?
		if(!isset($this->results[$this->currentTest])) {
			//create array
			$this->results[$this->currentTest] = array(
				'tests' => array(),
				'pass' => 0,
				'fail' => 0,
			);
		}
		//result as string
		$resultStr = $result ? 'pass' : 'fail';
		//log result
		$this->results[$this->currentTest][$resultStr]++;
		//get backtrace
		$backtrace = debug_backtrace();
		//cache backtrace info
		$testData = array(
			'result' => $resultStr,
			'message' => $message,
			'type' => $backtrace[1]['function'],
			'file' => $backtrace[1]['file'],
			'line' => $backtrace[1]['line'],
		);
		//add to results
		$this->results[$this->currentTest]['tests'][] = $testData
		//store result?
		if($this->logger) {
			$this->logger->debug($this->title . ': ' . $this->currentTest, $testData);
		}
		//return
		return $result;
    }

}