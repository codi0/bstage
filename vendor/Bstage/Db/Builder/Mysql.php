<?php

namespace Bstage\Db\Builder;

class MySql {

	public function query($type, $table, array $opts) {
		//set vars
		$sql = [];
		$params = [];
		$fields = null;
		$type = strtolower($type);
		//set defaults
		$defaults = [
			'fields' => ($type === 'select') ? '*' : [],
			'table' => $table,
			'join' => [],
			'where' => [],
			'search' => [],
			'group' => '',
			'having' => [],
			'order' => '',
			'limit' => '',
			'offset' => '',
		];
		//merge opts
		$opts = array_merge($defaults, $opts);
		//check opts
		foreach($opts as $k => $v) {
			//add to where?
			if(!isset($defaults[$k])) {
				$opts['where'][$k] = $v;
			}
		}
		//get table alias
		$exp = explode(' ', $opts['table']);
		$alias = isset($exp[1]) ? $exp[1] : ($opts['join'] ? $opts['table'] : '');
		//set type
		$sql[] = strtoupper($type);
		//set fields?
		if($type === 'select') {
			//format fields
			$fields = $this->fields($opts['fields'], $alias);
			//inject search params?
			if($opts['search'] && $opts['search']['term']) {
				$opts['fields'] = $fields;
				$opts = $this->injectSearch($opts, $alias);
				$fields = $opts['fields'];	
			}
			//add fields
			$sql[] = $fields;
		}
		//set from?
		if($type === 'select' || $type === 'delete') {
			$sql[] = 'FROM';
		}
		//set into?
		if($type === 'insert') {
			$sql[] = 'INTO';
		}
		//set table
		$sql[] = $opts['table'];
		//set insert?
		if($type === 'insert') {
			//set vars
			$values = [];
			//format insert values
			foreach($opts['fields'] as $key => $val) {
				//is scalar?
				if(!is_scalar($val)) {
					unset($opts['fields'][$key]);
					continue;
				}
				//add value
				$values[] = '?';
				//add param
				$params[] = $val;
			}
			//insert values
			$sql[] = '(' . implode(', ', array_keys($opts['fields'])) . ') VALUES (' . implode(', ', $values) . ')';
		}
		//set update?
		if($type === 'update') {
			//set vars
			$values = [];
			//format insert values
			foreach($opts['fields'] as $key => $val) {
				//self reference?
				if(strpos($val, $key) === 0) {
					$values[] = $key . '=' . $val;
					continue;
				}
				//build sql
				$values[] = $key . '=?';
				//add param
				$params[] = $val;
			}
			//update values
			$sql[] = 'SET ' . implode(', ', $values);
		}
		//set joins?
		if($opts['join']) {
			$sql[] = $this->join($opts['join'], $alias);
		}
		//set having?
		if($opts['where']) {
			$sql[] = 'WHERE ' . $this->where($opts['where'], $alias, $params);
		}
		//set group by?
		if($opts['group']) {
			$sql[] = 'GROUP BY ' . $this->fields($opts['group'], $alias);
		}
		//set having?
		if($opts['having']) {
			$sql[] = 'HAVING ' . $this->where($opts['having'], $alias, $params);
		}
		//set order?
		if($opts['order']) {
			$sql[] = 'ORDER BY ' . $this->fields($opts['order'], $alias);
		}
		//set limit?
		if($opts['limit']) {
			$sql[] = 'LIMIT ' . intval($opts['limit']);
		}
		//set offset?
		if($opts['offset']) {
			$sql[] = 'OFFSET ' . intval($opts['offset']);
		}
		//return
		return [
			'sql' => implode(' ', $sql),
			'params' => $params,
			'singular' => ($opts['limit'] && $opts['limit'] == 1),
		];
	}

	public function fields($fields, $table=null) {
		//fields to array?
		if(!is_array($fields)) {
			$fields = array_map('trim', explode(',', $fields));
		}
		//format fields
		foreach($fields as $k => $v) {
			//remove field?
			if(!is_scalar($v)) {
				unset($fields[$k]);
				continue;
			}
			//stop here?
			if(!$table || preg_match('/\.|\(|\s/', $v)) {
				continue;
			}
			//add table prefix to field
			$fields[$k] = $table . '.' . $v;
		}
		//return
		return implode(', ', $fields);
	}

	public function join($joins, $table) {
		//set vars
		$sql = [];
		$joins = (array) ($joins ?: []);
		//wrap joins?
		if($joins && !isset($joins[0])) {
			$joins = [ $joins ];
		}
		//loop through joins
		foreach($joins as $join) {
			//raw join
			if(is_string($join)) {
				$sql[] = $join;
				continue;
			}
			//set params
			$join = array_merge([
				'type' => 'inner',
				'table' => '',
				'on' => [],
			], $join);
			//add join?
			if($join['table']) {
				//join table
				$tmp = strtoupper($join['type']) . ' JOIN ' . $join['table'];
				//use on?
				if($join['on']) {
					//is array?
					if(is_array($join['on'])) {
						//format array
						$join['on'] = array_merge([
							'local' => 'id',
							'foreign' => '',
							'compare' => '=',
						], $join['on']);
						//add local table?
						if($table && strpos($join['on']['local'], '.') === false) {
							$join['on']['local'] = $table . '.' . $join['on']['local'];
						}
						//add remote table?
						if(strpos($join['on']['foreign'], '.') === false) {
							$join['on']['foreign'] = $join['table'] . '.' . $join['on']['foreign'];
						}
						//convert to string
						$join['on'] = $join['on']['local'] . ' ' . $join['on']['compare'] . ' ' . $join['on']['foreign'];
					}
					//add on clause
					$tmp .= ' ON ' . $join['on'];
				}
				//store
				$sql[] = $tmp;
			}
		}
		//return
		return implode(' ', $sql);
	}

	public function where($where, $table=null, array &$params=array()) {
		//set vars
		$sql = [];
		$where = (array) ($where ?: []);
		//wrap array?
		if(!empty($where)) {
			if(isset($where['fields']) || isset($where['logic'])) {
				$where = [ $where ];
			} elseif(!isset($where[0]) || !isset($where[0]['fields'])) {
				$where = [[ 'fields' => $where ]];
			}
		}
		//loop through array
		foreach($where as $key => $val) {
			//value to array?
			if(!is_array($val)) {
				$val = [ $key => $val ];
			}
			//wrap value?
			if(!isset($val['fields'])) {
				$val = [ 'fields' => $val ];
			}
			//add default keys
			$val = array_merge([
				'logic' => 'and',
			], $val);
			//set vars
			$clause = [];
			$val['logic'] = strtolower($val['logic']);
			//loop through fields
			foreach($val['fields'] as $name => $meta) {
				//is raw sql?
				if(is_numeric($name) && is_string($meta)) {
					$clause[] = $meta;
					continue;
				}
				//nested clause?
				if(is_array($meta) && isset($meta['fields'])) {
					$clause[] = $this->where($meta, $table, $params);
					continue;
				}
				//is value?
				if(!is_array($meta) || isset($meta[0])) {
					$meta = [ 'value' => $meta ];
				}
				//format meta
				$meta = array_merge([
					'value' => '',
					'compare' => '=',
				], $meta);
				//add table prefix to field?
				if($table && strpos($name, '.') === false) {
					$name = $table . '.' . $name;
				}
				//is null?
				if(is_null($meta['value'])) {
					continue;
				}
				//is array?
				if(is_array($meta['value'])) {
					//is empty?
					if(!$meta['value']) {
						continue;
					}
					//single value?
					if(count($meta['value']) == 1) {
						$meta['value'] = $meta['value'][0];
					} else {
						//set operator
						$clause[] = $name . ' IN(' . implode(',', array_fill(0, count($meta['value']), '?')) . ')';
						//add params
						foreach($meta['value'] as $p) {
							$params[] = $p;
						}
						//next
						continue;
					}
				}
				//build sql
				$clause[] = $name . ' ' . $meta['compare'] . ' ?';
				//add param
				$params[] = $meta['value'];
			}
			//to string
			$clause = implode(' ' . strtoupper($val['logic']) . ' ', $clause);
			//add brackets?
			if($clause && $val['logic'] === 'or') {
				$clause = '(' . $clause . ')';
			}
			//add sql
			$sql[] = $clause;
		}
		//return
		return implode(' ', $sql);
	}

	public function boolean($term) {
		//set vars
		$quotes = [];
		$term = str_ireplace([ ' and ', ' or ', ' not ' ], [ ' +', ' ', ' -' ], trim($term));
		//remove quoted strings
		$term = preg_replace_callback('/\"([^\"]*)\"/', function($matches) use(&$quotes) {
			$quotes[] = $matches[0];
			return '%%' . (count($quotes) - 1) . '%%';
		}, $term);
		//simple word stemming
		$term = preg_replace_callback('/(^|\+|\(|\s)(\w+)/', function($matches) {
			$stemmed = preg_replace('/(ed|ies|ing|ery)$/i', '', $matches[2]);
			return $matches[1] . $stemmed . ($matches[2] === $stemmed ? '' : '*');
		}, $term);
		//add back quoted strings
		foreach($quotes as $k => $v) {
			$term = str_replace('%%' . $k . '%%', $v, $term);
		}
		//is single term?
		if(!preg_match('/[\s|\"|\*]/', $term)) {
			$term = $term . '*';
		}
		//return
		return preg_replace('/\s+/', ' ', $term);
	}

	public function escape($str) {
		//escape string
		return strtr($str, array(
			"\x00" => '\x00',
			"\n" => '\n',
			"\r" => '\r',
			"\\" => '\\\\',
			"'" => "\'",
			'"' => '\"',
			"\x1a" => '\x1a'
		));
	}

	protected function injectSearch(array $opts, $alias) {
		//set vars
		$where = $groups = [];
		$fields = $this->fields($opts['search']['fields'], $alias);
		$term = $this->escape($this->boolean($opts['search']['term']));
		$score = (float) (isset($opts['search']['score']) ? $opts['search']['score'] : 0);
		$count = isset($opts['search']['count']) && $opts['search']['count'];
		//group fields by table
		foreach(explode(', ', $fields) as $val) {
			//default group
			$key = '';
			//has prefix?
			if(strpos($val, '.') !== false) {
				$key = explode('.', $val)[0];
			}
			//create group?
			if(!isset($groups[$key])) {
				$groups[$key] = [];
			}
			//add field
			$groups[$key][] = $val;
		}
		//reset fields
		$fields = [];
		//loop through groups
		foreach($groups as $key => $val) {
			//to string
			$val = implode(', ', $val);
			//field
			$fields[] = "MATCH($val) AGAINST('$term' IN BOOLEAN MODE)";
			//where
			$where[] = "MATCH($val) AGAINST('$term' IN BOOLEAN MODE) > " . (float) $score;
		}
		//add fields?
		if(!$count) {
			if(count($fields) > 1) {
				$opts['fields'] .= ', (' . implode(') + (', $fields) . ') as score';
			} else {
				$opts['fields'] .= ', ' . $fields[0] . ' as score';
			}
		}
		//add where
		if(count($where) > 1) {
			$opts['where'][] = '(' . implode(' OR ', $where) . ')';
		} else {
			$opts['where'][] = $where[0];
		}
		//add order?
		if(!$count && !$opts['order']) {
			$opts['order'] = 'score DESC';
		}
		//return
		return $opts;	
	}

}