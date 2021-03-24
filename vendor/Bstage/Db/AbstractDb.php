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
			$this->builder = new \Bstage\Db\Builder\Mysql([
				'prefix' => $this->prefix,
			]);
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
		return $this->builder->escape($str);
	}

	public function insertId() {
		return (int) $this->insertId;
	}

	public function rowsAffected() {
		return (int) $this->rowsAffected;
	}

	public function select($table, $opts=[]) {
		//is ID?
		if(!is_array($opts)) {
			$opts = [ 'id' => $opts ];
		}
		//set vars
		$table = $this->formatTable($table);
		//build sql
		$builder = $this->builder->query('select', $table, $opts);
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
		//build sql
		$builder = $this->builder->query('insert', $table, [
			'fields' => $values,
		]);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->insertId() ?: true;
	}

	public function update($table, array $set, array $where=[]) {
		//set vars
		$opts = isset($set['fields']) ? $set : [ 'fields' => $set ];
		//add where?
		if($where) {
			$opts['where'] = $where;
		}
		//build sql
		$builder = $this->builder->query('update', $table, $opts);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->rowsAffected();
	}

	public function delete($table, array $opts=[]) {
		//build sql
		$builder = $this->builder->query('delete', $table, $opts);
		//valid query?
		if(!$this->query($builder['sql'], $builder['params'])) {
			return false;
		}
		//return
		return $this->rowsAffected();
	}

	public function createSchema($schema) {
		//is file?
		if(strpos($schema, ' ') === false) {
			//file exists?
			if(!is_file($schema)) {
				return false;
			}
			//extract schema
			$schema = file_get_contents($schema);
		}
		//loop through queries
		foreach(explode(';', $schema) as $query) {
			//trim query
			$query = trim($query);
			//execute query?
			if(!empty($query)) {
				$this->query($query);
			}
		}
		//success
		return true;
	}

	protected function formatTable($table) {
		return $this->builder->table($table);
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

	protected function getTimeOffset() {
		//calculate offset
		$now = new \DateTime();
		$mins = $now->getOffset() / 60;
		$sign = ($mins < 0 ? -1 : 1);
		$mins = abs($mins);
		$hrs = floor($mins / 60);
		$mins -= $hrs * 60;
		//format offset
		return sprintf('%+d:%02d', $hrs * $sign, $mins);
	}

}