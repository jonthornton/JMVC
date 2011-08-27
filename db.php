<?php

namespace jmvc;

class Db {
	
	protected $write_db;
	protected $read_db;
    protected static $instance;
	
	public static $stats;
	public static $queries;
	
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
	
	public static function instance()
	{
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		
		return self::$instance;
	}
	
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
	
	public function quote_date($value)
	{
		if (!empty($value) && is_numeric($value)) {
			$value = date('Y-m-d H:i:s', $value);
		}
		
		return $this->quote($value);
	}
	
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
	
	public function insert_row($table, $row)
	{
		$query = $this->make_insert($table, $row);
		return $this->insert($query);
	}
	
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
	
	public function get_row($query)
	{
		$result = $this->select($query);
		
		if (!$result || $result->num_rows != 1) {
			return false;
		}
		
		if ($result->field_count == 1) {
			$row = $result->fetch_row();
			return $row[0];
		} else {
			return $result->fetch_assoc();
		}
	}
	
	public function get_rows($query, $key=false, $callback=false)
	{
		$result = $this->select($query);
		
		if (!$result || $result->num_rows == 0) {
			return false;
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
	
	public function select($query)
	{
		self::$stats['select']++;
		return $this->do_query($query);
	}
	
	public function delete($query)
	{
		self::$stats['delete']++;
		$this->do_query($query, true);
		return $this->write_db->affected_rows;
	}
	
	public function insert($query)
	{
		self::$stats['insert']++;
		$this->do_query($query, true);
		return $this->write_db->insert_id;
	}
	
	public function update($query)
	{
		self::$stats['update']++;
		$this->do_query($query, true);
		return $this->write_db->affected_rows;
	}
}