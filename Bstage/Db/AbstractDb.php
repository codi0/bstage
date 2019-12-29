<?php

namespace Bstage\Db;

abstract class AbstractDb {

	protected $dbh = null;
	protected $logger = null;
	protected $debug = false;

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
	}

	abstract public function getDbh();

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

	public function select($table, array $filters=array(), $limit=null) {
		//set vars
		$method = 'getAll';
		$whereSql = array();
		$table = $this->tableName($table);
		//format filters?
		if($filters = array_merge(array( 'FIELDS' => '*', 'WHERE' => [], 'PARAMS' => [], 'GROUP' => '', 'ORDER' => '' ), $filters)) {
			//loop through array
			foreach($filters as $key => $val) {
				//reserved key?
				if($key && in_array($key, array( 'FIELDS', 'WHERE', 'PARAMS', 'GROUP', 'ORDER' ))) {
					continue;
				}
				//unset key
				unset($filters[$key]);
				//update key
				$filters['WHERE'][$key] = $val;
			}
		}
		//format fields?
		if(is_array($filters['FIELDS'])) {
			$filters['FIELDS'] = implode(',', $filters['FIELDS']);
		}
		//build where sql
		$whereSql = $this->buildWhereSql($filters['WHERE'], $filters['PARAMS']);
		//build select
		$sql = 'SELECT ' . $filters['FIELDS'] . ' FROM ' . $table . ($whereSql ? ' WHERE ' . implode(' AND ', $whereSql) : '');
		//add group by?
		if($filters['GROUP']) {
			$sql .= ' GROUP BY ' . $filters['GROUP'];
		}
		//add order by?
		if($filters['ORDER']) {
			$sql .= ' ORDER BY ' . $filters['ORDER'];
		}
		//add limit?
		if($limit > 0) {
			$sql .= ' LIMIT ' . $limit;
			$method = ($limit == 1) ? 'getRow' : $method;
		}
		//return
		return $this->$method($sql, $filters['PARAMS']);
	}

	public function insert($table, array $values) {
		//set vars
		$params = array();
		$valueSql = array();
		$table = $this->tableName($table);
		//format insert params
		foreach($values as $key => $val) {
			//build sql
			$valueSql[] = '?';
			//add param
			$params[] = $val;
		}
		//build query
		$sql = 'INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', $valueSql) . ')';
		//valid query?
		if(!$this->query($sql, $params)) {
			return false;
		}
		//return
		return $this->insertId;
	}

	public function update($table, array $fields, array $where, $limit=null) {
		//set vars
		$params = array();
		$fieldSql = array();
		$whereSql = array();
		$table = $this->tableName($table);
		//format fields params
		foreach($fields as $key => $val) {
			//self reference?
			if(strpos($val, $key) === 0) {
				$fieldSql[] = $key . '=' . $val;
				continue;
			}
			//build sql
			$fieldSql[] = $key . '=?';
			//add param
			$params[] = $val;
		}
		//build where sql
		$whereSql = $this->buildWhereSql($where, $params);
		//build select
		$sql = 'UPDATE ' . $table . ' SET ' . implode(',', $fieldSql) . ($whereSql ? ' WHERE ' . implode(' AND ', $whereSql) : '');
		//add limit?
		if($limit > 0) {
			$sql .= ' LIMIT ' . $limit;
		}
		//valid query?
		if(!$this->query($sql, $params)) {
			return false;
		}
		//return
		return $this->rowsAffected;
	}

	public function delete($table, array $where, $limit=null) {
		//set vars
		$params = array();
		$whereSql = array();
		$table = $this->tableName($table);
		//build where sql
		$whereSql = $this->buildWhereSql($where, $params);
		//build query
		$sql = 'DELETE FROM ' . $table . ($whereSql ? ' WHERE ' . implode(' AND ', $whereSql) : '');
		//add limit?
		if($limit > 0) {
			$sql .= ' LIMIT ' . $limit;
		}
		//valid query?
		if(!$this->query($sql, $params)) {
			return false;
		}
		//return
		return $this->rowsAffected;
	}

	public function tableName($sql) {
		//set vars
		$prefix = $this->prefix;
		//replace {prefix} placeholder
		$sql = str_replace('{prefix}', $prefix, $sql);
		//replace {table} placeholder
		$sql = preg_replace_callback('/{[a-z0-9\-\_]+}/i', function($match) use($prefix) {
			return $prefix . str_replace(array( '{', '}' ), '', $match[0]);
		}, $sql);
		//add prefix manually?
		if($prefix && stripos($sql, ' ') === false && stripos($sql, $prefix) !== 0) {
			$sql = $prefix . $sql;
		}
		//return
		return trim($sql);
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

	abstract public function query($sql, array $params=array());

	public function insertId() {
		return (int) $this->insertId;
	}

	public function rowsAffected() {
		return (int) $this->rowsAffected;
	}

	abstract public function getOne($sql, array $params=array());

	abstract public function getRow($sql, array $params=array());

	abstract public function getCol($sql, array $params=array());

	abstract public function getAll($sql, array $params=array());

	abstract public function close();

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

	public function getQueries() {
		return $this->queries;
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

	protected function addError($error, $sql) {
		//add to array
		$this->errors[] = $error;
		//log error?
		if($this->logger) {
			$this->logger->error($error, array( 'sql' => $sql ));
		}
		//throw error?
		if($this->debug) {
			throw new \Exception($error);
		}
	}

	protected function buildWhereSql($where, array &$params=array()) {
		//set vars
		$sql = array();
		//loop through array
		foreach((array) $where as $key => $val) {
			//is numeric?
			if(is_numeric($key)) {
				//raw query
				$sql[] = $val;
				//next
				continue;
			}
			//is array?
			if(is_array($val)) {
				//set operator
				$sql[] = $key . ' IN(' . implode(',', array_fill(0, count($val), '?')) . ')';
				//add params
				foreach($val as $v) {
					$params[] = $v;
				}
				//next
				continue;
			}
			//equal or not?
			if($val && $val[0] === '!') {
				$op = '!=';
				$val = substr($val, 1);
			} else {
				$op = '=';
			}
			//build sql
			$sql[] = $key . $op . '?';
			//add param
			$params[] = $val;
		}
		//return
		return $sql;
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

}