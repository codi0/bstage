<?php

namespace Bstage\Db\Builder;

class MySql {

	public function buildQuery($type, $table, array $opts) {
		//set vars
		$sql = [];
		$params = [];
		$type = strtolower($type);
		$parts = [ 'fields', 'joins', 'where', 'group', 'having', 'order', 'limit', 'offset' ];
		//format opts
		$opts = array_merge([
			'fields' => ($type === 'select') ? '*' : [],
			'joins' => [],
			'where' => [],
			'group' => '',
			'having' => [],
			'order' => '',
			'limit' => '',
			'offset' => '',
		], $opts);
		//check opts
		foreach($opts as $k => $v) {
			//add to where?
			if(!in_array($k, $parts)) {
				$opts['where'][$k] = $v;
			}
		}
		//set type
		$sql[] = strtoupper($type);
		//set fields?
		if($type === 'select') {
			$sql[] = $this->buildFields($opts['fields'], $table);
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
		$sql[] = $table;
		//set insert?
		if($type === 'insert') {
			//set vars
			$values = [];
			//format insert values
			foreach($opts['fields'] as $key => $val) {
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
		if($opts['joins']) {
			$sql[] = $this->buildJoins($opts['joins'], $table);
		}
		//set having?
		if($opts['where']) {
			$sql[] = 'WHERE ' . $this->buildWhere($opts['where'], $table, $params);
		}
		//set group by?
		if($opts['group']) {
			$sql[] = 'GROUP BY ' . $this->buildFields($opts['group'], $table);
		}
		//set having?
		if($opts['having']) {
			$sql[] = 'HAVING ' . $this->buildWhere($opts['having'], $table, $params);
		}
		//set order?
		if($opts['order']) {
			$sql[] = 'ORDER BY ' . $this->buildFields($opts['order'], $table);
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

	public function buildFields($fields, $table) {
		//fields to array?
		if(!is_array($fields)) {
			$fields = array_map('trim', explode(',', $fields));
		}
		//format fields
		foreach($fields as $k => $v) {
			//table already added?
			if(strpos($v, '.') !== false) {
				continue;
			}
			//parse function?
			if(preg_match('/\(([^\)]+)\)/', $v, $match)) {
				$v = str_replace($match[1], $this->buildFields($match[1], $table), $v);
			}
			//add table name
			$fields[$k] = $table . '.' . $v;
		}
		//return
		return implode(', ', $fields);
	}

	public function buildJoins($join, $table) {
		//TO DO
		return $join;
	}

	public function buildWhere($where, $table, array &$params=array()) {
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
					$clause[] = $this->buildWhere($meta, $table, $params);
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
				//add table to name?
				if(strpos($name, '.') === false) {
					$name = $table . '.' . $name;
				}
				//is array?
				if(is_array($meta['value'])) {
					//set operator
					$clause[] = $name . ' IN(' . implode(',', array_fill(0, count($meta['value']), '?')) . ')';
					//add params
					foreach($meta['value'] as $p) {
						$params[] = $p;
					}
					//next
					continue;
				}
				//build sql
				$clause[] = $name . $meta['compare'] . '?';
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

}