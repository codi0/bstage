<?php

namespace Bstage\View\Dom;

/**
 * DOM node class
 *
 * Represents a DOM node.
 */
class Node {

    /**
     * Node object
     * @var object
     */
	public $node = null;

    /**
     * Node character set
     * @var string
     */
	protected $charset = '';

    /**
     * Is HTML flag
     * @var boolean
     */
	protected $isHtml = true;

    /**
     * Node level
     * @var integer
     */
	protected $level = 0;

    /**
     * Node level inclusive flag
     * @var boolean
     */
	protected $levelInc = false;

    /**
     * Constructor - sets object properties
     *
     * @param  object $node
     * @param  string $charset
     " @param  boolean $isHtml
     * @return void
     */
	public function __construct($node, $charset='', $isHtml=true) {
		//set vars
		$this->node = $node;
		$this->charset = $this->charset;
		$this->isHtml = $this->isHtml;
	}

    /**
     * Has matching nodes
     *
     * @return boolean
     */
	public function has() {
		//set vars
		$found = false;
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				if(!empty($old)) {
					$found = true;
					break 2;
				}
			}
		}
		//return
		return $found;
	}

    /**
     * Return matching nodes
     *
     * @param  boolean $first
     * @param  string $type
     * @return mixed
     */
	public function get($type='string', $first=true) {
		//set vars
		$res = array();
		$class = __CLASS__;
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//temp data
			$temp = array();
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//string or node?
				if(stripos($type, 'node') === 0) {
					//create node object
					$res[] = new $class(array(
						'node' => $type == 'node:clone' ? $old->cloneNode(true) : $old,
						'charset' => $this->charset,
						'isHtml' => $this->isHtml,
					));
				} else {
					//convert to string
					$old = $this->createString($old);
					$temp[] = $old;
				}
			}
			//to string?
			if(stripos($type, 'node') !== 0) {
				$res[] = implode("", $temp);
			}
			//first only?
			if($first) {
				$res = isset($res[0]) ? $res[0] : null;
				break;
			}
		}
		//reset
		$this->reset();
		//return
		return $res;
	}

    /**
     * Return all matches, as text
     *
     * @return mixed
     */
	public function getAll($type='string') {
		return $this->get($type, false);
	}

    /**
     * Return children of matching node(s)
     *
     * @param  boolean $asNodes
     * @param  boolean $first
     * @return mixed
     */
	public function getInner($asNodes=false, $first=true) {
		return $this->children()->get($asNodes, $first);
	}

    /**
     * Replace matching node(s)
     *
     * @param  mixed $data
     * @return $this
     */
	public function set($data) {
		//create nodes
		$newNodes = $this->createNodes($data);
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//set vars
				$parent = $old->parentNode;
				$sibling = $old->nextSibling;
				//remove old node
				$parent->removeChild($old);
			}
			//valid parent?
			if(!isset($parent) || !$parent) {
				continue;
			}
			//add new nodes
			foreach($newNodes as $new) {
				if($sibling) {
					$parent->insertBefore($new->cloneNode(true), $sibling);
				} else {
					$parent->appendChild($new->cloneNode(true));
				}
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Replace children of matching node(s)
     *
     * @param  mixed $data
     * @return $this
     */
	public function setInner($data) {
		return $this->children()->set($data);
	}

    /**
     * Fill matching node(s)
     *
     * @param  array $data
     * @return $this
     */
	public function fill(array $data) {
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				$this->fillNodeRecursive($old, $data);
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Fill children of matching node(s)
     *
     * @param  array $data
     * @return $this
     */
	public function fillInner(array $data) {
		return $this->children()->fill($data);
	}

    /**
     * Wrap matching node(s)
     *
     * @param  mixed $data
     * @return $this
     */
	public function wrap($data) {
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//create nodes
			$newNodes = $this->createNodes($data);
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//set vars
				$parent = $old->parentNode;
				$sibling = $old->nextSibling;
				//update new nodes
				foreach($newNodes as $new) {
					$new->appendChild($old->cloneNode(true));
				}
				//remove old node
				$parent->removeChild($old);
			}
			//valid parent?
			if(!isset($parent) || !$parent) {
				continue;
			}
			//add new nodes
			foreach($newNodes as $new) {
				if($sibling) {
					$parent->insertBefore($new->cloneNode(true), $sibling);
				} else {
					$parent->appendChild($new->cloneNode(true));
				}
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Wrap children of matching node(s)
     *
     * @param  mixed $data
     * @return $this
     */
	public function wrapInner($data) {
		return $this->children()->wrap($data);
	}

    /**
     * Unwrap matching node(s)
     *
     * @return $this
     */
	public function unwrap() {
		//set vars
		$remove = array();
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//get data
				$hash = spl_object_hash($old->parentNode);
				//already processed?
				if(isset($remove[$hash])) {
					continue;
				}
				//add to queue
				$remove[$hash] = $old->parentNode;
				//loop through all children
				foreach($old->parentNode->childNodes as $child) {
					if($old->parentNode->parentNode) {
						$old->parentNode->parentNode->insertBefore($child->cloneNode(true), $old->parentNode);
					}
				}
			}
		}
		//loop through removals
		foreach($remove as $r) {
			$r->parentNode->removeChild($r);
		}
		//return
		return $this->reset();	
	}

    /**
     * Unwrap children of matching node(s)
     *
     * @return $this
     */
	public function unwrapInner() {
		return $this->children()->unwrap();
	}

    /**
     * Remove matching node(s)
     *
     * @return $this
     */
	public function remove() {
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				$old->parentNode->removeChild($old);
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Remove children of matching node(s)
     *
     * @return $this
     */
	public function removeInner() {
		return $this->children()->remove();
	}

    /**
     * Insert child node(s)
     *
     * @param  string $data
     * @param  boolean $first
     * @return $this
     */
	public function insertChild($data, $first=false) {
		//create nodes
		$newNodes = $this->createNodes($data);
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//loop through new nodes
				foreach($newNodes as $new) {
					//node has children?
					if($first && $old->firstChild) {
						$old->insertBefore($new->cloneNode(true), $old->firstChild);
					} else {
						$old->appendChild($new->cloneNode(true));
					}
				}
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Insert node(s) before current node
     *
     * @param  string $data
     * @return $this
     */
	public function insertBefore($data) {
		//create nodes
		$newNodes = $this->createNodes($data);
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//loop through new nodes
				foreach($newNodes as $new) {
					$old->parentNode->insertBefore($new->cloneNode(true), $old);
				}
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Insert node(s) after current node
     *
     * @param  string $data
     * @return $this
     */
	public function insertAfter($data) {
		//create nodes
		$newNodes = $this->createNodes($data);
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//loop through new nodes
				foreach($newNodes as $new) {
					//node has sibling?
					if($old->nextSibling) {
						$old->parentNode->insertBefore($new->cloneNode(true), $old->nextSibling);
					} else {
						$old->parentNode->appendChild($new->cloneNode(true));
					} 
				}
			}
		}
		//return
		return $this->reset();
	}

    /**
     * Get node attribute
     *
     * @param  string $key
     * @param  boolean $first
     * @param  boolean $incNode
     * @return mixed
     */
	public function getAttr($key, $first=true, $incNode=false) {
		//set vars
		$res = array();
		$key = strtolower($key);
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				if($incNode) {
					$res[] = array( 'value' => $old->getAttribute($key), 'node' => $old );
				} else {
					$res[] = $old->getAttribute($key);
				}
			}
		}
		//reset
		$this->reset();
		//return
		return $first ? (isset($res[0]) ? $res[0] : null) : $res;
	}

    /**
     * Set node attribute(s)
     *
     * @param  string|array $key
     * @param  string $value
     * @return $this
     */
	public function setAttr($key, $val=null) {
		//set vars
		$key = is_array($key) ? $key : array( $key => $val );
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through array
			foreach($this->prepNodes($nodes) as $old) {
				//loop through attr
				foreach($key as $k => $v) {
					$old->setAttribute($k, $v);
				}
			}
		}
		//reset
		$this->reset();
		//return
		return $this;
	}

    /**
     * Remove node attribute(s)
     *
     * @param  string|array $key
     * @return $this
     */
	public function removeAttr($key) {
		//set vars
		$key = is_array($key) ? $key : array( $key );
		//loop through matches
		foreach($this->initNode() as $nodes) {
			//loop through nodes
			foreach($this->prepNodes($nodes) as $old) {
				//loop through attr
				foreach($key as $k) {
					$old->removeAttribute($k);
				}
			}
		}
		//reset
		$this->reset();
		//return
		return $this;
	}

    /**
     * Does class exist on any matching elements?
     *
     * @param  string $name
     * @return boolean
     */
	public function hasClass($name) {
		//get all elements
		$elements = $this->getAttr('class', false, true);
		//loop through elements
		foreach($elements as $el) {
			//get parts
			$parts = array_map('trim', explode(" ", $el['value']));
			//loop through parts
			foreach($parts as $k => $v) {
				if($v && $v == $name) {
					return true;
				}
			}
		}
		//not found
		return false;
	}

    /**
     * Add class to all matching elements
     *
     * @param  string $name
     * @return $this
     */
	public function addClass($name) {
		//get elements
		$elements = $this->getAttr('class', false, true);
		//loop through array
		foreach($elements as $el) {
			//get parts
			$found = false;
			$parts = array_map('trim', explode(" ", $el['value']));
			//loop through parts
			foreach($parts as $k => $v) {
				if($v && $v == $name) {
					$found = true;
					break;
				}
			}
			//add class?
			if($found === false) {
				$el['node']->setAttribute('class', trim($el['value'] . ' ' . $name));
			}
		}
		//chain it
		return $this;
	}

    /**
     * Remove class from all matching elements
     *
     * @param  string $name
     * @return $this
     */
	public function removeClass($name) {
		//get elements
		$elements = $this->getAttr('class', false, true);
		//loop through array
		foreach($elements as $el) {
			//get parts
			$found = false;
			$parts = array_map('trim', explode(" ", $el['value']));
			//loop through parts
			foreach($parts as $k => $v) {
				if($v && $v == $name) {
					unset($parts[$k]);
					$found = true;
				}
			}
			//remove class?
			if($found !== false) {
				$el['node']->setAttribute('class', implode(" ", $parts));
			}
		}
		//chain it
		return $this;
	}

    /**
     * Select parent node
     *
     * @param  integer $num
     * @param  boolean $inc
     * @return $this
     */
	public function parent($num=1, $inc=false) {
		//set level
		$this->level = $this->level + (int) $num;
		//include all levels?
		$this->levelInc = (bool) $inc;
		//return
		return $this;
	}

    /**
     * Select child nodes
     *
     * @param  integer $num
     * @param  boolean $inc
     * @return $this
     */
	public function children($num=1, $inc=false) {
		//set level
		$this->level = $this->level - (int) $num;
		//include all levels?
		$this->levelInc = (bool) $inc;
		//return
		return $this;
	}

    /**
     * Reset object data
     *
     * @return $this
     */
	public function reset() {
		//clear vars
		$this->level = 0;
		$this->levelInc = false;
		//return
		return $this;
	}

    /**
     * Return DOMNOde as array for processing
     *
     * @return array
     */
	protected function initNode() {
		//convert object to array?
		if($this->node instanceOf \DOMNode) {
			$this->node = array( $this->node );
		}
		//return
		return $this->node ?: array();
	}

    /**
     * Prepare nodes for processing
     *
     * @param  object $node
     * @return array
     */
	protected function prepNodes(\DOMNode $node) {
		//level unchanged?
		if($this->level == 0) {
			return array( $node );
		}
		//set vars
		$res = array();
		$prop = $this->level > 0 ? 'parentNode' : 'childNodes';
		$limit = $this->level > 0 ? $this->level : $this->level * -1;
		//loop through levels
		for($z=0; $z < $limit; $z++) {
			//prop found?
			if(!isset($node->$prop) || !$node->$prop) {
				return false;
			}
			//reset array?
			if(!$this->levelInc) {
				$res = array();
			}
			//update node
			$node = $node->$prop;
			//prepare nodes
			if(!$node instanceOf \DOMNodeList) {
				if($node !== (array) $node) {
					$node = array($node);
				}
			}
			//loop through array
			foreach($node as $old) {
				if($old->nodeType == 1 || trim($old->nodeValue)) {
					$res[] = $old;
				}
			}
		}
		//return
		return $res;
	}

    /**
     * Load string into DOMDocument
     *
     * @param  string $data
     * @return object
     */
	protected function createDom($data='') {
		//trim data
		$data = trim($data);
		//get charset?
		if(!$this->charset) {
			$this->charset = $this->detectCharset($data);
		}
		//check data encoding
		$data = $this->checkEncoding($data, $this->charset);
		//create DOM object
		$dom = new \DOMDocument('1.0', $this->charset);
		//get load method
		$method = $this->isHtml ? 'loadHTML' : 'loadXML';
		//load to dom?
		if(strlen($data) > 0) {
			libxml_use_internal_errors(true);
			$dom->$method($data);
			libxml_clear_errors();
		}
		//remove cdata?
		if(stripos($data, '<script') !== false && stripos($data, '<![CDATA') === false) {
			$this->removeCdata($dom);
		}
		//return
		return $dom;
	}

    /**
     * Convert string to nodes array
     *
     * @param  mixed $data
     * @return array
     */
	protected function createNodes($data) {
		//set vars
		$res = array();
		$class = __CLASS__;
		$method = __FUNCTION__;
		$data = is_array($data) ? $data : array($data);
		//set dom object
		foreach($this->initNode() as $node) {
			$dom = $node->ownerDocument;
			break;
		}
		//does dom exist?
		if(!isset($dom) || !$dom) {
			return $res;
		}
		//loop through data
		foreach($data as $d) {
			if($d instanceOf \DOMNode) {
				//object
				$res[] = $d;
			} elseif($d instanceOf $class) {
				$res[] = is_array($d->node) ? $d->node[0] : $d->node;
			} elseif(is_array($d)) {
				//array
				$res += $this->$method($d);
			} elseif(is_string($d)) {
				//is raw text?
				if($d && strip_tags($d) === $d) {
					//create text node
					$res[] = $dom->createTextNode($d);
				} else {
					//load dom
					$nodes = null;
					$tmp = $this->createDom($d);
					//search nodes
					foreach(array( 'body', 'head', 'html' ) as $el) {
						//match found?
						if($nodes = $tmp->getElementsByTagName($el)->item(0)) {
							break;
						}
					}
					//import nodes?
					if($nodes && $nodes->childNodes) {
						//loop through children
						foreach($nodes->childNodes as $node) {
							$res[] = $dom->importNode($node, true);
						}
					}
				}
			}
		}
		//return
		return $res;
	}

    /**
     * Convert node to string
     *
     * @param  object $node
     * @return string
     */
	protected function createString(\DOMNode $node) {
		return $node->ownerDocument->saveXML($node);
	}

    /**
     * Fill node row with data recursively
     *
     * @param  object $node
     * @param  array $data
     * @return void
     */
	protected function fillNodeRecursive($node, array $data) {
		//set vars
		$count = 0;
		$method = __FUNCTION__;
		//has next sibling?
		if(!$current = $this->findNextNode($node->firstChild, 1)) {
			return;
		}
		//loop through data
		foreach($data as $k => $v) {
			//set vars
			$count++;
			$updated = false;
			//set node value
			if(is_array($v) && isset($v[0])) {
				$this->$method($current, $v);
			} else {
				//delete child nodes
				while($current->firstChild) {
					$current->removeChild($current->firstChild);
				}
				//get new nodes
				$new = $this->createNodes($v);
				//loop through nodes
				foreach($new as $n) {
					$current->appendChild($n);
				}
			}
			//get next sibling?
			if($next = $this->findNextNode($current->nextSibling, 1)) {
				$current = $next;
			} elseif($count < count($data)) {
				$current = $current->parentNode->appendChild($current->cloneNode(true));
			}
		}
	}

    /**
     * Find next node, by type
     *
     * @param  object $node
     * @param  integer $type
     * @return object|null
     */
	protected function findNextNode($node, $type) {
		//loop it!
		while($node) {
			//type match?
			if($node->nodeType == (int) $type) {
				return $node;
			}
			//update node
			$node = $node->nextSibling;
		}
		//not found
		return null;
	}

    /**
     * Detect charset
     *
     * @param  string $data
     * @param  string $charset
     * @param  string
     */
	protected function detectCharset($data, $charset=null) {
		//auto-detect?
		if(!$charset && function_exists('mb_detect_encoding')) {
			//auto-detect
			$charset = mb_detect_encoding($data);
			//convert ascii?
			if($charset == 'ASCII') {
				$charset = 'UTF-8';
			}
		}
		//return (upper case)
		return strtoupper($charset ? $charset : 'UTF-8');
	}

    /**
     * Check data encoding
     *
     * @param  string $data
     * @param  string $charset
     * @param  string
     */
	protected function checkEncoding($data, $charset=null) {
		//convert encoding?
		if(function_exists('mb_convert_encoding')) {
			$data = mb_convert_encoding($data, "HTML-ENTITIES", $charset);
		}
		//remove invalid characters?
		if(function_exists('iconv')) {
			$data = @iconv($charset, $charset . "//IGNORE", $data);
		}
		//return
		return $data;
	}

    /**
     * Remove CDATA from script nodes
     *
     * @param  object $dom
     * @param  void
     */
	protected function removeCdata($dom) {
		//has script nodes?
		if(!$scripts = $dom->getElementsByTagName('script')) {
			return;
		}
		//loop through scripts
		foreach($scripts as $s) {
			//is first node cdata?
			if($s->firstChild && $s->firstChild->nodeType == 4) {
				$cdata = $s->removeChild($s->firstChild);
				$text = $dom->createTextNode($cdata->nodeValue);
				$s->appendChild($text);
			}
		}
	}

}