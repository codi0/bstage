<?php

namespace Bstage\Debug;

class ErrorHandler {

	protected $debug = false;
	protected $debugBar = true;
	protected $handled = false;

	protected $startTime = 0;
	protected $startMem = 0;

	protected $prev = null;
	protected $events = null;
	protected $logger = null;

	protected $alwaysDisplay = array( 'critical', 'error' );

	protected $errorLevels = array(
		E_PARSE => 'critical',
		E_COMPILE_ERROR => 'critical',
		E_CORE_ERROR => 'critical',
		E_ERROR => 'error',
		E_USER_ERROR => 'error',
		E_RECOVERABLE_ERROR => 'error',
		E_WARNING => 'warning',
		E_USER_WARNING => 'warning',
		E_CORE_WARNING => 'warning',
		E_COMPILE_WARNING => 'warning',
		E_NOTICE => 'notice',
		E_USER_NOTICE => 'notice',
		E_DEPRECATED => 'notice',
		E_USER_DEPRECATED => 'notice',
		E_STRICT => 'info',
	);

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//start timer
		$this->startTime = microtime(true);
		$this->startMem = memory_get_usage();
		//show debug bar?
		if($this->debug && $this->debugBar && $this->events) {
			$this->events->add('app.response', [ $this, 'debugBarEvent' ]);
		}
	}

	public function setLogger($logger) {
		//set property
		$this->logger = $logger;
		//chain it
		return $this;
	}

	public function handle() {
		//already handled?
		if($this->handled) return;
		//update flag
		$this->handled = true;
		//report all errors
		error_reporting(E_ALL);
		//do not display errors
		@ini_set('display_errors', $this->debug ? 1 : 0);
		@ini_set('display_startup_errors', $this->debug ? 1 : 0);
		//handle exceptions
		$this->prev = set_exception_handler(array( $this, 'handleException' ));
		//handle legacy errors
		set_error_handler(array( $this, 'handleError' ));
		//handle fatal errors
		register_shutdown_function(array( $this, 'handleShutdown' ));
	}

	public function handleException($e) {
		//stop here?
		static $stopErrors = false;
		if($stopErrors) return;
		//set vars
		$num = 10;
		$trace = array();
		$level = $this->getExLevel($e);
		$buffer = (int) ini_get('zlib.output_compression');
		//clean buffer
		while(ob_get_level() > $buffer) {
			ob_end_clean();
		}
		//log error?
		if($this->logger) {
			$this->logger->$level($e->getMessage(), array( 'file' => $e->getFile(), 'line' => $e->getLine() ));
		}
		//delegate to prev?
		if(!$this->debug && $this->prev) {
			return call_user_func($this->prev, $e);
		}
		//ignore error?
		if(!$this->debug && !in_array($level, $this->alwaysDisplay)) {
			return;
		}
		//error event?
		if($this->events) {
			//EVENT: error.handle
			$this->events->dispatch('error.handle', array(
				'exception' => $e,
				'level' => $level,
				'debug' => $this->debug,
			));
		}
		//set content type?
		if(!headers_sent()) {
			header('Content-Type: text/html');
		}
		//production error?
		if(!$this->debug) {
			echo 'An error has occurred. Please try again.';
			exit();
		}
		//no more errors
		$stopErrors = true;
		//build debug html
		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html>' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8" />' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />' . "\n";
		$html .= '<title>Debug: Error occurred</title>' . "\n";
		$html .= '<style>' . "\n";
		$html .= '* { box-sizing: border-box; }' . "\n";
		$html .= 'body { width: 100%; padding: 10px; margin: 0; }' . "\n";
		$html .= 'a:hover { cursor: pointer; }' . "\n";
		$html .= 'h1 { margin-top: 0; }' . "\n";
		$html .= 'table { border: 0; }' . "\n";
		$html .= 'td { padding: 3px; }' . "\n";
		$html .= 'td:first-child { width: 170px; }' . "\n";
		$html .= '.alt tr:nth-child(odd) { background: #ddd; }' . "\n";
		$html .= '.snippet { display: none; }' . "\n";
		$html .= '.snippet-link, .snippet-link:visited { color: blue; }' . "\n";
		$html .= '#snippet-0 { display: block; }' . "\n";
		$html .= '#snippet-link-0 { color: red; }' . "\n";
		$html .= '</style>' . "\n";
		$html .= '<script>' . "\n";
		$html .= 'function showSnippet(id) {' . "\n";
		$html .= '	var els = document.getElementsByClassName("snippet");' . "\n";
		$html .= '	for(var i=0; i < els.length; i++) {' . "\n";
		$html .= '		els[i].style.display = "none";' . "\n";
		$html .= '		document.getElementById("snippet-link-" + i).style.color = "blue";' . "\n";
		$html .= '	}' . "\n";
		$html .= '	document.getElementById("snippet-" + id).style.display = "block";' . "\n";
		$html .= '	document.getElementById("snippet-link-" + id).style.color = "red";' . "\n";
		$html .= '	return false;' . "\n";
		$html .= '}' . "\n";
		$html .= '</script>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body>' . "\n";
		//check steps
		foreach($e->getTrace() as $t) {
			if(isset($t['file']) && $t['file'] && $t['line']) {
				if($t['file'] !== __FILE__) {
					$trace[] = $t;
				}
			}
		}
		//add final step?
		if(!$trace || $trace[0]['file'] !== $e->getFile() || $trace[0]['line'] !== $e->getLine()) {
			array_unshift($trace, array( 'file' => $e->getFile(), 'line' => $e->getLine() ));
		}
		//loop through trace
		foreach($trace as $k => $v) {
			//set vars
			$snippet = '';
			$line = $v['line'];
			//parse file?
			if(strpos($v['file'], ' ') === false) {
				//get file content
				$contents = file($v['file']);
				//loop through content
				for($i = $line - $num; $i <= $line + $num; $i++) {
					if(isset($contents[$i])) {
						$snippet .= ($i+1) . ' ' . $contents[$i] . "\n";
					}
				}
			}
			//format snippet?
			if(!empty($snippet)) {
				$snippet = highlight_string('<?php' . "\n" . $snippet, true);
				$snippet = str_replace('<br /><br />', '<br />', $snippet);
				$snippet = str_replace('&lt;?php<br />', '', $snippet);
				$snippet = str_replace('>' . $line . '&nbsp;', '><span style="color:red; font-weight:bold;">' . $line . '</span>&nbsp;', $snippet);
			}
			//add to trace
			$trace[$k]['snippet'] = $snippet;
		}
		//show error message
		$html .= '<h1>' . ucfirst($level) . ': ' . strip_tags($e->getMessage()) . '</h1>' . "\n";
		$html .= '<table id="trace">' . "\n";
		//loop through trace
		foreach($trace as $k => $v) {
			$html .= '<tr><td><a id="snippet-link-' . $k . '" class="snippet-link" onclick="return showSnippet(' . $k . ');">Line ' . $v['line'] . '</a></td><td>' . $v['file'] . '</td></tr>' . "\n";
		}
		$html .= '</table>' . "\n";
		//loop through trace
		foreach($trace as $k => $v) {
			$html .= '<div id="snippet-' . $k . '" class="snippet">' . "\n";
			$html .= '<p style="background-color:#eee; padding:10px;">' . "\n";
			$html .= ($v['snippet'] ?: 'No code snippet available. Eval?') . "\n";
			$html .= '</p>' . "\n";
			$html .= '</div>' . "\n";
		}
		//end html
		$html .= '</body>' . "\n";
		$html .= '</html>';
		//display
		echo $html;
		exit();
	}

	public function handleError($severity, $message, $file, $line) {
		//convert to exception
		$e = new \ErrorException($message, 0, $severity, $file, $line);
		//handle exception
		$this->handleException($e);
	}

	public function handleShutdown() {
		//error found?
		if($error = error_get_last()) {
			//convert to exception
			$e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
			//handle exception
			$this->handleException($e);
		}
	}

	public function debugBar(array $queries=[]) {
		//debug vars
		$time = number_format(microtime(true) - $this->startTime, 5);
		$mem = number_format((memory_get_usage() - $this->startMem) / 1024, 0);
		$peak = number_format(memory_get_peak_usage() / 1024, 0);
		//debug data
		$debug  = '<div id="debug-bar" style="width:100%; font-size:12px; text-align:left; padding:10px; margin-top:20px; background:#eee;">' . "\n";
		$debug .= '<div style="margin-bottom:5px;"><b>Debug bar</b></div>' . "\n";
		$debug .= '<div>Time: ' . $time . 's | Mem: ' . $mem . 'kb | Peak: ' . $peak . 'kb | Queries: ' . count($queries) . '</div>' . "\n";
		//db queries?
		if($queries) {
			$debug .= '<div style="margin-top:10px;"><b>Database queries</b></div>' . "\n";
			$debug .= '<ol style="margin:0; padding-left:15px;">' . "\n";
			foreach($queries as $q) {
				$debug .= '<li style="margin-top:5px;">' . $q . '</li>' . "\n";
			}
			$debug .= '</ol>' . "\n";
		}
		$debug .= '</div>' . "\n";
		//return
		return $debug;
	}

	public function debugBarEvent($event, $app) {
		//is html response?
		if($event->response->getMediaType() !== 'html') {
			return;
		}
		//get output
		$output = $event->response->getContents();
		//show debug vars?
		if($event->request->getAttribute('primary')) {
			//get queries
			$queries = $app->db->getQueries();
			//get debug bar
			$debug = $this->debugBar($queries);
			//add before </footer> or </body>
			if(preg_match('/<\/(footer|body)>/i', $output)) {
				$output = preg_replace('/<\/(footer|body)>/i', $debug. '</$1>', $output, 1);
			} else {
				$output .= "\n" . trim($debug);
			}
		}
		//update response body
		$event->response->withContents($output);
	}

	protected function getExLevel($e) {
		//get severity
		$severity = method_exists($e, 'getSeverity') ? $e->getSeverity() : 1;
		//return
		return isset($this->errorLevels[$severity]) ? $this->errorLevels[$severity] : 'error';
	}

}