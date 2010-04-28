<?php

namespace jmvc;

class Db {
	
	protected $db;
    protected static $instance;
	
	public static $stats;
	public static $queries;
	
	private function __construct()
	{
		$this->db = new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		self::$stats = array('select'=>0, 'insert'=>0, 'update'=>0, 'delete'=>0);
	}
	
	public function __destruct()
	{
		if ($this->db)
			$this->db->close();
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
		if (is_numeric($value)) {
			return $value;
		}
		
		$value = "'" . $this->db->real_escape_string($value) . "'";

		return $value;
	}
	
	public function make_insert($table, $row)
	{
		$fields = '';
		$values = '';
		
		foreach($row as $key => $value) {
			$fields .= "`$key`, ";
			
			if (is_array($value)) {
				$values .= $value['raw'].', ';
			} else if ($value == NULL) {
				$values .= 'NULL, ';
			} else {
				$values .= $this->quote($value).', ';
			}
		}
		
		$fields = substr($fields, 0, -2);
		$values = substr($values, 0, -2);
		
		return "INSERT IGNORE INTO $table($fields) VALUES($values)";
	}
	
	public function make_update($table, $data, $where)
	{
		$fields = '';
		foreach($data as $key => $value) {
			
			if (is_array($value)) {
				$fields .= "$key = ". $value['raw'].", ";
			} else if ($value == NULL) {
				$fields .= $key.' = NULL, ';
			} else {
				$fields .= "$key = ". $this->quote($value) .", ";
			}
		}
		
		$fields = substr($fields, 0, -2);
		
		$where_sql = Array();
		foreach ($where as $key=>$value) {
			array_push($where_sql, $key.'='.$this->quote($value));
		}
		
		$where = implode($where_sql, ' AND ');
		
		return "UPDATE $table SET $fields WHERE $where";	
	}
	
	public function do_query($query)
	{
		$result = $this->db->query($query);
		if (!$result) {
		
			$message = $this->db->error;
			
			throw new \ErrorException($message, 0, 1, $query, 0);
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
	
	public function get_rows($query, $key=false)
	{
		$result = $this->select($query);
		
		if (!$result || $result->num_rows == 0) {
			return false;
		}
		
		$outp = array();
		
		if ($result->field_count == 1) {
			while ($row = $result->fetch_row()) {
				array_push($outp, $row[0]);
			}
		} else {
			if ($key) {
				while ($row = $result->fetch_assoc()) {
					$outp[$row[$key]] = $row;
				}
			} else {
				while ($row = $result->fetch_assoc()) {
					array_push($outp, $row);
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
		$this->do_query($query);
	}
	
	public function insert($query)
	{
		self::$stats['insert']++;
		$this->do_query($query);
		return $this->db->insert_id;
	}
	
	public function update($query)
	{
		self::$stats['update']++;
		$this->do_query($query);
	}
}