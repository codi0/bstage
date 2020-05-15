<?php

namespace Bstage\Db;

abstract class AbstractDb {

	protected $dbh;
	protected $logger;
	protected $builder;
	protected $resultClass = 'Bstage\Db\Result\TableRow';

	protected $host = 'localhost';
	protected $user = '';
	protected $pass = '';
	protected $name = '';

	protected $prefix = '';
	protected $charset = 'utf8';
	protected $schemaFile = '';

	protected $insertId = 0;
	protected $rowsAffected = 0;

	protected $queries = [];
	protected $errors = [];

	protected $debug = false;

    public static function create(array $opts=array()) {
		//get class name
		if(isset($opts['dbh']) && $opts['dbh']) {
			$class = __NAMESPACE__ . '\\' . (($opts['dbh'] instanceof \PDO) ? 'Pdo' : 'Mysqli');
		} elseif(isset($opts['driver']) && $opts['driver']) {
			$class = __NAMESPACE__ . '\\' . ucfirst($opts['driver']);
		} else {
			$class = get_called_class();
		}
        //create object
        return new $class($opts);
    }

	public function __construct(array $opts=array()) {
		//set properties
		foreach($opts as $k => $v) {
			if(property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		//use default builder?
		if(!$this->builder) {
			$this->builder = new \Bstage\Db\Builder\Mysql;
		}
	}

	abstract public function getDbh();

	public function getBuilder() {
		return $this->builder;
	}

	public function getQueries() {
		return $this->queries;
	}

	public function getLastQuery() {
		//count queries
		$count = count($this->queries);
		//return last query
		return $count > 0 ? $this->queries[$count-1] : null;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function getLastError() {
		//count errors
		$count = count($this->errors);
		//return last error
		return $count > 0 ? $this->errors[$count-1] : null;
	}

	public function setDbh($dbh) {
		//set property
		$this->dbh = $dbh;
		//chain it
		return $this;
	}

	public function setPrefix($prefix) {
		//set property
		$this->prefix = $prefix;
		//chain it
		return $this;
	}

	public function setLogger($logger) {
		//set property
		$this->logger = $logger;
		//chain it
		return $this;
	}

	abstract public function query($sql, array $params=array());

	abstract public function getOne($sql, array $params=array());

	abstract public function getRow($sql, array $params=array());

	abstract public function getCol($sql, array $params=array());

	abstract public function getAll($sql, array $params=array());

	abstract public function close();

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

	public function insertId() {
		return (int) $this->insertId;
	}

	public function rowsAffected() {
		return (int) $this->rowsAffected;
	}

	public function select($table, array $opts=[]) {
		//set vars
		$table = $this->formatTable($table);
		//build sql
		$builder = $this->builder->buildQuery('select', $table, $opts);
		//get query method
		$method = $builder['singular'] ? 'getRow' : 'getAll';
		//get result?
		if(!$res = $this->$method($builder['sql'], $builder['params'])) {
			return $res;
		}
		//wrap class?
		if($this->resultClass) {
			//row or collection?
			if($method === 'getRow') {
				$res = new $this->resultClass([ 'data' => $res, 'db' => $this, 'table' => $table ]);
			} else {
				//loop through results
				foreach($res as $k => $v) {
					$res[$k] = new $this->resultClass([ 'data' => $v, 'db' => $this, 'table' => $table ]);
				}
			}
		}
		//return
		return $res;
	}

	public function insert($table, array $values) {
		//set vars
		$table = $this->formatTable($table);
		//build sql
		$builder = $this->builder->buildQuery('insert', $table, [
			'fields' => $values,
		]);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->insertId();
	}

	public function update($table, array $set, array $where=[]) {
		//set vars
		$table = $this->formatTable($table);
		$opts = isset($set['fields']) ? $set : [ 'fields' => $set ];
		//add where?
		if($where) {
			$opts['where'] = $where;
		}
		//build sql
		$builder = $this->builder->buildQuery('update', $table, $opts);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->rowsAffected();
	}

	public function delete($table, array $opts=[]) {
		//set vars
		$table = $this->formatTable($table);
		//build sql
		$builder = $this->builder->buildQuery('delete', $table, $opts);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->rowsAffected();
	}

	public function search($table, $fields, $term, $score=0, array $opts=[]) {
		//set vars
		$table = $this->formatTable($table);
		$fields = $this->builder->buildFields($fields, $table);
		$term = $this->escape($this->formatSearchTerm($term));
		//format opts
		$opts = array_merge([
			'fields' => [ '*' ],
			'where' => [],
		], $opts);
		//add field
		$opts['fields'][] = "MATCH($fields) AGAINST('$term' IN BOOLEAN MODE) as score";
		//add where
		$opts['where'][] = "MATCH($fields) AGAINST('$term' IN BOOLEAN MODE) > " . (float) $score;
		//build sql
		$builder = $this->builder->buildQuery('select', $table, $opts);
		//run query
		return $this->getAll($builder['sql'], $builder['params']);
	}

	public function createSchema() {
		//has schema file?
		if(!$this->schemaFile || !is_file($this->schemaFile)) {
			return false;
		}
		//update database schema?
		if($sql = file_get_contents($this->schemaFile)) {
			//split queries
			foreach(explode(';', $sql) as $query) {
				if($query = trim($query)) {
					$this->query($query);
				}
			}
		}
		//success
		return true;
	}

	protected function formatTable($sql) {
		//replace {prefix} placeholder
		$sql = str_replace('{prefix}', $this->prefix, $sql);
		//add prefix manually?
		if($this->prefix && strpos($sql, ' ') === false && strpos($sql, '.') === false) {
			if(strpos($sql, $this->prefix) !== 0) {
				$sql = $this->prefix . $sql;
			}
		}
		//return
		return trim($sql);
	}

	protected function formatParams(array $params) {
		//loop through params
		foreach($params as $k => $v) {
			if(!is_scalar($v)) {
				$params[$k] = json_encode($v);
			}
		}
		//return
		return $params;
	}

	protected function formatSearchTerm($term) {
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
		//return
		return $term;
	}

	protected function addError($error, $sql) {
		//add to array
		$this->errors[] = $error;
		//log error?
		if($this->logger) {
			$this->logger->error($error, array( 'sql' => $sql ));
		}
		//throw error?
		if($this->debug) {
			if($q = $this->getLastQuery()) {
				$error .= ' [' . $q . ']';
			}
			throw new \Exception($error);
		}
	}

}