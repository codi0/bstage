<?php

namespace Bstage\View\Dom;

/**
 * DOM manipulation class
 *
 * Manipulate html data via the DOM, from the server side.
 */
class Document extends Node {

    /**
     * DOM document object
     * @var object
     */
	protected $dom = null;

    /**
     * Random token
     * @var string
     */
	protected $token = '';

    /**
     * Is fragment flag
     * @var boolean
     */
	protected $isFragment = false;

    /**
     * Constructor - sets object properties
     *
     * @param  object|string $input
     * @return void
     */
	public function __construct($input=null) {
		//set token
		$this->token = $this->generateRandStr(8);
		//load data
		if($input instanceOf \DomDocument) {
			$this->dom = $input;
			$this->isHtml = $this->dom->xmlVersion ? false : true;
		} elseif(is_string($input) && $input) {
			$this->load($input);
		}
	}

    /**
     * Load data into DOM
     *
     * @param  string $data
     * @return $this
     */
	public function load($data) {
		//set vars
		$data = trim($data);
		$token = $this->token;
		$this->isHtml = true;
		$this->isFragment = false;
		//is url?
		if(strpos($data, 'http') === 0 && strpos($data, '://') !== false) {
			$data = trim(file_get_contents($data));
		}
		//is xml?
		if(stripos($data, '<?xml') === 0) {
			$this->isHtml = false;
		}
		//is html?
		if($this->isHtml && stripos($data, "<!DOCTYPE") !== 0) {
			//html fragment
			$this->isFragment = true;
			//remove tags
			$data = preg_replace(array('/<html.*?>/i', '/<\/html>/i', '/<head.*?>.*?<\/head>/is', '/<body.*?>/i', '/<\/body>/i'), '', $data);
			//format fragment
			$data = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=' . strtolower($this->charset ?: 'utf-8') . '" /></head><body>' . $data . '</body></html>';
		} elseif(!$this->isHtml && stripos($data, '<?xml') !== 0) {
			//xml fragment
			$this->isFragment = true;
			//format data
			$data = '<?xml version="1.0"?>' . $data;
		}
		//script content hack
		$data = preg_replace_callback('/<script\b[^>]*>([\s\S]*?)<\/script>/ims', function($matches) use($token) {
			return str_replace($matches[1], str_replace(array( '<', '>' ), array( '$%' . $token, '%$' . $token ), $matches[1]), $matches[0]);
		}, $data);
		//load dom
		$this->dom = $this->createDom($data);
		//return
		return $this;
	}

    /**
     * Save data
     *
     * @param  boolean $pretty
     * @return string
     */
	public function save($pretty=false) {
		//set vars
		$token = $this->token;
		//save output
		if($this->isHtml) {
			$data = @$this->dom->saveHTML();
		} else {
			$data = @$this->dom->saveXML($this->dom->documentElement);
		}
		//script content hack
		$data = preg_replace_callback('/<script\b[^>]*>([\s\S]*?)<\/script>/ims', function($matches) use($token) {
			return str_replace($matches[1], str_replace(array( '$%' . $token, '%$' . $token ), array( '<', '>' ), $matches[1]), $matches[0]);
		}, $data);
		//remove xml header?
		if($this->isHtml || $this->isFragment) {
			$data = preg_replace('/<\?xml.*?\?>/i', '', $data);
		}
		//html fragment?
		if($this->isHtml && $this->isFragment) {
			$data = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|meta|body))[^>]*>\s*~i', '', $data);
		}
		//format output?
		if($pretty !== false) {
			$data = $this->formatOutput($data);
		}
		//return
		return trim($data);
	}

    /**
     * Load css selector
     *
     * @param  string $selector
     * @return $this
     */
	public function select($selector) {
		//reset node
		$this->node = null;
		//load class?
		if(!class_exists('bkdotcom\CssXpath', false)) {
			require_once(__DIR__ . '/bkdotcom/CssXpath.php');
		}
		//translate to xpath
		$query = \bkdotcom\CssXpath::cssToXpath($selector);
		//run query
		return $this->query($query);
	}

    /**
     * Execute xpath query
     *
     * @param  string $query
     * @throws Exception if xpath query is invalid
     * @return $this
     */
	public function query($query) {
		//set vars
		$query = trim($query);
		$xpath = new \DOMXPath($this->dom);
		//run query
		$this->node = @$xpath->query($query);
		//valid query?
		if($this->node === false) {
			throw new \Exception("Invalid xpath query - " . $query);
		}
		//return
		return $this;
	}

    /**
     * Return document node
     *
     * @return object
     */
	public function document() {
		return $this->dom->documentElement;
	}

    /**
     * Translate css selector to xpath query
     *
     * @param  string $query
     * @param  string
     */
	protected function css2xpath($query) {
		//set vars
		$index = 1;
		$lastQuery = null;
		$parts = array( '//' );
		//loop until end
		while(strlen($query) > 0 && $query != $lastQuery) {
			//set last query
			$lastQuery = $query;
			$query = trim($query);
			//valid length?
			if(strlen($query) == 0) {
				break;
			}
			//element identifier
			//.field | #field | input
			if(preg_match('/^([#.]?)([a-z0-9\\*_-]*)((\|)([a-z0-9\\*_-]*))?/i', $query, $m)) {
				if(!isset($m[1])) {
					//set index
					$parts[$index] = isset($m[5]) ? $m[5] : $m[2];
				} elseif($m[1] == '#') {
					//id
					array_push($parts, "*[@id='" . $m[2] . "']");
				} elseif($m[1] == '.') {
					//class
					array_push($parts, "*[@class and contains(concat(' ',normalize-space(@class),' '),' " .  $m[2] . " ')]"); 
				} else {
					//other
					array_push($parts, $m[0]);
				}
				//remove query match
				$query = substr($query, strlen($m[0]));
			}
			//attribute selector
			//input[id="username"] | input[class!="field"]
			if(preg_match('/^\[([a-z0-9\-\_]*)(\*|\~|\!|\|)?=?(.*)?\]/i', $query, $m)) {
				//set attribute value
				$m[3] = isset($m[3]) ? trim(trim($m[3], '"'), "'") : '';
				//select option
				if(!$m[3] || $m[3] == "*") {
					//attribute exists (wildcard)
					array_push($parts, "*[@" . $m[1] . "]");
				} elseif($m[2] == '*') {
					//attribute contains value
					array_push($parts, "*[contains(@" . $m[1] . ", '" . $m[3] . "')]");
				} elseif($m[2] == "~") {
					//attribute contains value, in multiple values
					array_push($parts, "*[@" . $m[1] . " and contains(concat(' ',normalize-space(@" . $m[1] . "),' '),' " .  $m[3] . " ')]");
				} elseif($m[2] == "|" || $m[2] == '^') {
					//attribute starts with
					array_push($parts, "*[@" . $m[1] . "='" . $m[3] . "' or starts-with(@" . $m[1] . ",'" . $m[3] . "')]");
				} elseif($m[2] == "!") {
					//attribute not equal to
					array_push($parts, "*[@" . $m[1] . "!='" . $m[3] . "']");
				} else {
					//attribute equal to
					array_push($parts, "*[@" . $m[1] . "='" . $m[3] . "']");
				}
				//remove query match
				$query = substr($query, strlen($m[0]));
			}
			//child selector
			//.field:first-child | .field:nth-child(3)
			if(preg_match('/^:(first|last|nth)-child(\(([0-9]+)\))?/i', $query, $m)) {
				if($m[1] == "first") {
					//first child
					$m[2] = '1';
				} elseif($m[1] == "last") {
					//last child
					$m[2] = 'last()';
				}
				if(isset($m[2])) {
					//valid child found
					array_push($parts, "/*[" . (isset($m[3]) ? $m[3] : $m[2]) . "]");
				}
				//remove query match
				$query = substr($query, strlen($m[0]));		
			}
			//first / last selectors
			//.field:first | .field:last(-2)
			if(preg_match('/^:(first|last)(\((\+|\-)([0-9]+)\))?/i', $query, $m)) {
				if($m[1] == "first" && (!isset($m[2]) || $m[3] == "+")) {
					//first element
					array_push($parts, "[" . (isset($m[4]) ? $m[4] + 1 : '1') . "]");
				} elseif($m[1] == "last" && (!isset($m[2]) || $m[3] == "-")) {
					//last element
					array_push($parts, "[last()" . (isset($m[4]) ? $m[4] * -1 : '') . "]");
				}
				//remove query match
				$query = substr($query, strlen($m[0]));
			}
			//greater / less than selectors
			//.field:lt(1) | .field:gt(2)
			if(preg_match('/^:(gt|lt)\(([0-9])\)/i', $query, $m)) {
				//convert to symbol
				$m[1] = $m[1] == 'gt' ? ">" : "<";
				//add position rule
				array_push($parts, "[position()" . $m[1] . $m[2] . "]");
				//remove query match
				$query = substr($query, strlen($m[0]));		
			}										
			//skip psuedo selectors
			if(preg_match('/^:([a-z_-])+/i', $query, $m)) {
				while($m) {
					//remove query match
					$query = substr($query, strlen($m[0]));
					//loop query until complete
					preg_match('/^:([a-z_-])+/i', $query, $m);
				}
			}
			//combining selectors
			//.field input | .field > input | .field + input
			if(preg_match('/^(\s*[>+\s])?/i', $query, $m)) {
				if(strlen($m[0]) > 0) {
					if(strpos($m[0], ">")) {
						//child selector
						array_push($parts, "/");
					} elseif(strpos($m[0], "+")) {
						//next sibling
						array_push($parts, "/following-sibling::");
					} else {
						//new selector
						array_push($parts, "//");
					}
					//update index
					$index = count($parts);
					//remove query match 
					$query = substr($query, strlen($m[0]));
				}
			}
			//commas
			//.field, .field2
			if(preg_match('/^\s*,/i', $query, $m)) {
				//include 'or' syntax
				array_push($parts, " | ", "//");
				//update index
				$index = count($parts) - 1;
				//remove query match
				$query = substr($query, strlen($m[0]));
			}
		}
		//convert array to string
		$parts = implode("", $parts);
		//return query
		return preg_replace('/([a-z0-9])(\*)/i', '$1', $parts);
	}

	/**
	 * Generate random string
	 *
	 * @param  integer $length
	 * @return string
	 */
	protected function generateRandStr($length) {
		//set vars
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		//return output
		return substr(str_shuffle(str_repeat($chars, mt_rand(1, 10))), 1, $length);
	}

	/**
	 * Format output (tabs and line breaks)
	 *
	 * @param  string $data
	 * @return string
	 */
	protected function formatOutput($data) {
		//set vars
		$output = "";
		$padding = 0;
		$wasText = false;
		$data = preg_replace('/<[^<]*>/', "\n$0\n", $data);
		//convert to tokens
		$token = strtok($data, "\n");
		//start token loop
		while($token !== false) {
			//set vars
			$indent = 0;
			$empty = false;
			//valid token?
			if(!$token = trim($token)) {
				$token = strtok("\n");
				continue;
			}
			//not a tag?
			if($token[0] != '<') {
				$output = rtrim($output) . $token;
				$token = strtok("\n");
				$wasText = true;
				continue;
			}
			//check options
			if(preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) {
				$indent = 0;
			} elseif(preg_match('/^<\/\w/', $token, $matches)) {
				$padding--;
			} elseif(preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) {
				$indent = 1;
			}
			//get current line
			$line = $wasText ? $token : str_pad($token, strlen($token) + $padding, "\t", STR_PAD_LEFT);
			$lineTrim = trim($line);
			//empty element?
			if(substr($lineTrim, 0, 2) == '</') {
				$outputTrim = trim($output);
				$outputExp = explode("<", $outputTrim);
				$outputLast = $output_exp[count($outputExp)-1];
				if($outputLast[0] != '/' && $outputLast[strlen($outputLast)-1] == '>' && $outputLast[strlen($outputLast)-2] != '/') {
					$output = $outputTrim . $lineTrim . "\n";
					$empty = true;
				}
			}
			//add now?
			if(!$empty) {
				$output .= $line . "\n";
			}
			//next token
			$token = strtok("\n");
			$padding += $indent;
			$wasText = false;
		}
		//return
		return $output;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function initNode() {
		//create dom?
		if(!$this->dom) {
			$this->load('')->select('body');
		}
		//call parent
		return parent::initNode();
	}

}