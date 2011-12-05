<?php

namespace jmvc;

class Db {

	protected $write_db;
	protected $read_db;
    protected static $instance;

	public static $stats;
	public static $queries;

	/**
	 * Singleton classe
	 */
	private function __construct()
	{
		$config = $GLOBALS['_CONFIG']['db'];
		$this->write_db = new \mysqli($config['write']['host'], $config['write']['user'], $config['write']['pass'], $config['write']['name']);

		if (isset($config['read'])) {
			$read_config = $config['read'][rand(0, count($config['read'])-1)];
			$this->read_db = new \mysqli($read_config['host'], $read_config['user'], $read_config['pass'], $read_config['name']);
		} else {
			$this->read_db = $this->write_db;
		}

		self::$stats = array('select'=>0, 'insert'=>0, 'update'=>0, 'delete'=>0);
	}

	public function __destruct()
	{
		if ($this->read_db) {
			$this->read_db->close();
		}
		if ($this->write_db && $this->write_db != $this->read_db) {
			$this->write_db->close();
		}
	}

	/**
	 * Retrieve the DB instance
	 * @return \jmvc\Db
	 */
	public static function instance()
	{
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	/**
	 * MySQL quote a value. Performs some type detection to try to match MySQL type. Protects against SQL injection.
	 * @param string $value
	 * @return string
	 */
	public function quote($value)
	{
		if ($value === NULL) {
			return 'NULL';
		} else if (is_numeric($value)) {
			return $value;
		} else {
			return "'" . $this->read_db->real_escape_string($value) . "'";
		}
	}

	/**
	 * MySQL quote a date. Accepts either datetime strings or Unix timestamps
	 * @param mixed $value
	 * @return string
	 */
	public function quote_date($value)
	{
		if (!empty($value) && is_numeric($value)) {
			$value = date('Y-m-d H:i:s', $value);
		}

		return $this->quote($value);
	}

	/**
	 * Quote a string without any type detection
	 * @param string $value
	 * @return string
	 */
	public function escape_string($value)
	{
		return $this->read_db->real_escape_string($value);
	}

	/**
	 * Generate a MySQL key=value argument pair
	 * @param string $key Table column
	 * @param string $value Argument value. Will be quoted.
	 * @param string $prefix Prefix to put on column names in SQL. Typically \jmvc\Model::$_find_prefix.
	 * @return string
	 */
	public function make_parameter($key, $value, $prefix=null)
	{
		if ($key == 'raw_sql') {
			// Raw (non-quoted) SQL
			return (is_array($value)) ? implode(' AND ', $value) : $value;
		}

		if (strpos($key, '.')) {
			list($prefix, $key) = explode('.', $key);
			$prefix .= '.';
		}

		if (is_array($value)) {
			if (empty($value)) { // NULL
				return $prefix.$key.' IS NULL';

			} else { // IN criteria
				$str = $prefix.$key.' IN(';
				foreach ($value as $val) {
					$str .= self::quote($val).', ';
				}
				return substr($str, 0, -2).')';
			}

		} else if ($value === NULL) { // NULL
			return $prefix.$key.' IS NULL';

		} else if (substr($key, -7) == '_before') { // DateTime range
			return $prefix.substr($key, 0, -7).' <= '.self::quote_date($value);

		} else if (substr($key, -6) == '_after') { // DateTime range
			return $prefix.substr($key, 0, -6).' >= '.self::quote_date($value);

		} else { // equals
			return $prefix.$key.'='.$this->quote($value);
		}
	}

	/**
	 * Generate SQL to insert associative array into MySQL table
	 * @param string $table
	 * @param array $row Array keys match column names
	 * @param array $field_types Array of field information. Keys match column names. Allowed values are 'datetime'.
	 * @return string
	 */
	public function make_insert($table, $row, $field_types=array())
	{
		$fields = '';
		$values = '';

		foreach($row as $key => $value) {
			$fields .= "`$key`, ";

			if (is_array($value)) {
				$values .= $value['raw'].', ';
			} else if ($value === NULL) {
				$values .= 'NULL, ';
			} else {

				if (isset($field_types[$key])) {
					switch ($field_types[$key]) {
						case 'date':
						case 'datetime':
							$values .= $this->quote_date($value).', ';
							break;

						default:
							$values .= $this->quote($value).', ';
							break;
					}
				} else {
					$values .= $this->quote($value).', ';
				}
			}
		}

		$fields = substr($fields, 0, -2);
		$values = substr($values, 0, -2);

		return "INSERT IGNORE INTO $table($fields) VALUES($values)";
	}

	/**
	 * Insert an array into MySQL. Wraps make_insert().
	 * @param string $table
	 * @param array $row
	 * @return int MySQL INSERT_ID
	 */
	public function insert_row($table, $row)
	{
		$query = $this->make_insert($table, $row);
		return $this->insert($query);
	}

	/**
	 * Generate SQL to update an existing row. Similar to make_insert().
	 * @param string $table
	 * @param array $data
	 * @param array $where Array of key=value pairs that will be used as WHERE criteria.
	 * @param array $field_types
	 * @return string
	 */
	public function make_update($table, $data, $where, $field_types=array())
	{
		$fields = '';
		foreach($data as $key => $value) {

			if (is_array($value)) {
				$fields .= "`$key` = ". $value['raw'].", ";
			} else if ($value === NULL) {
				$fields .= "`$key` = NULL, ";
			} else {
				if (isset($field_types[$key])) {
					switch ($field_types[$key]) {
						case 'date':
						case 'datetime':
							$fields .= "`$key` = ". $this->quote_date($value) .", ";
							break;

						default:
							$fields .= "`$key` = ". $this->quote($value) .", ";
							break;
					}
				} else {
					$fields .= "`$key` = ". $this->quote($value) .", ";
				}
			}
		}

		$fields = substr($fields, 0, -2);

		$where_sql = Array();
		foreach ($where as $key=>$value) {
			$where_sql[] = $key.'='.$this->quote($value);
		}

		$where = implode($where_sql, ' AND ');

		return "UPDATE $table SET $fields WHERE $where";
	}

	/**
	 * Execute a single SQL command.
	 * @param string $query
	 * @param bool $write In a multi-db environment, force query to go to the master db
	 * @return mysqli_result
	 */
	public function do_query($query, $write=false)
	{
		if ($write) {
			$db = $this->write_db;
		} else {
			$db = $this->read_db;
		}

		$start = microtime(true);
		$result = $db->query($query);
		$time = microtime(true) - $start;

		if (!$result) {
			$message = $db->error;
			throw new \ErrorException($message, 0, 1, $query, 0);
		}

		if (defined('DB_QUERY_STATS')) {
			self::$queries[] = array('query'=>$query, 'time'=>$time);
		}

		return $result;
	}

	/**
	 * Execute multiple SQL commands separated by ;
	 * @param string $query
	 * @return mysqli_result
	 */
	public function do_multi_query($query)
	{
		$result = $this->write_db->multi_query($query);

		if (!$result) {
			$message = $this->write_db->error;
			throw new \ErrorException($message, 0, 1, $query, 0);
		}

		while ($this->write_db->next_result()) {
			// need to clear out db results before issuing new queries
		}

		return $result;
	}

	/**
	 * Perform a select query and retreive a single row from the database. Will return null if the query
	 * returns more than one row.
	 * @param string $query
	 * @return mixed If return is a single column, data value will be returned (string/int). Otherwise,
	 * 					and associative array of the row data will be returned.
	 */
	public function get_row($query)
	{
		$result = $this->select($query);

		if (!$result || $result->num_rows != 1) {
			return null;
		}

		if ($result->field_count == 1) {
			$row = $result->fetch_row();
			return $row[0];
		} else {
			return $result->fetch_assoc();
		}
	}

	/**
	 * Retrieve multiple rows from the database.
	 * @param string $query
	 * @param bool $key If true, array keys match the ID column
	 * @param function $callback Function to filter returned data
	 * @return mixed Null if query returned no rows; array of associative arrays otherwise
	 */
	public function get_rows($query, $key=false, $callback=false)
	{
		$result = $this->select($query);

		if (!$result || $result->num_rows == 0) {
			return null;
		}

		$outp = array();

		if ($result->field_count == 1) {
			while ($row = $result->fetch_row()) {
				$outp[] = $row[0];
			}
		} else {
			if ($key) {
				while ($row = $result->fetch_assoc()) {
					if (!$callback || $callback($row)) $outp[$row[$key]] = $row;
				}
			} else {
				while ($row = $result->fetch_assoc()) {
					if (!$callback || $callback($row)) $outp[] = $row;
				}
			}
		}
		return $outp;
	}

	/**
	 * Perform a SELECT query
	 * @param string $query
	 * @return mysqli_result
	 */
	public function select($query)
	{
		self::$stats['select']++;
		return $this->do_query($query);
	}

	/**
	 * Perform a DELETE query
	 * @param string $query
	 * @return int Number of deleted rows
	 */
	public function delete($query)
	{
		self::$stats['delete']++;
		$this->do_query($query, true);
		return $this->write_db->affected_rows;
	}

	/**
	 * Perform an INSERT query
	 * @param string $query
	 * @return int INSERT_ID of row added
	 */
	public function insert($query)
	{
		self::$stats['insert']++;
		$this->do_query($query, true);
		return $this->write_db->insert_id;
	}

	/**
	 * Perform an UPDATE query
	 * @param string $query
	 * @return int Number of affected rows
	 */
	public function update($query)
	{
		self::$stats['update']++;
		$this->do_query($query, true);
		return $this->write_db->affected_rows;
	}
}
