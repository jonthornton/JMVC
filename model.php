<?php

namespace jmvc;

class Model {
	
	protected $_values = array();
	protected $_dirty_values = array();
	protected $_loaded = false;
	protected $_criteria = false;
	protected $_obj_id = false;
	
	protected static $_table;
	protected static $_find_query;
	protected static $_find_prefix = '';
	
	public function __construct($id=false)
	{
		if ($id) {
			if (is_array($id)) {
				$this->_criteria = $id;
			} else {
				$this->_criteria = array('id'=>$id);
			}
		}
	}
	
	public static function factory($id=false)
	{
		$type = get_called_class();
		$obj = new $type($id);
		
		if ($obj->valid()) {
			return $obj;
		} else {
			return false;
		}
	}
	
	public function __get($key)
	{
		if (isset($this->_dirty_values[$key])) {
			return $this->_dirty_values[$key];
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if (isset($this->_values[$key])) {
			return $this->_values[$key];
		}
		
		return null;
	}
	
	public function __set($key, $value)
	{
		$this->_dirty_values[$key] = $value;
	}
	
	public function set_raw($key, $value)
	{
		$this->_dirty_values[$key] = array('raw'=>$value);
	}
	
	public function __isset($key)
	{
		if (isset($this->_dirty_values[$key])) {
			return true;
		}

		if (!$this->_loaded) {
			$this->load();
		}
		
		if (isset($this->_values[$key])) {
			return true;
		}
		
		return false;
	}
	
	public function __unset($key)
	{
		unset($this->_dirty_values[$key]);
	}
	
	protected static function db()
	{
		return Db::instance();
	}
	
	public function load($data=false)
	{
		if (!$this->_criteria && $this->_obj_id) $this->_criteria = array('id'=>$this->_obj_id);
		if (!$data && $this->_criteria) {
			$data = self::find_one($this->_criteria);
		}
	
		$this->_loaded = true;
		
		if (!$data) {
			return false;
		}
		
		$this->_values = $data;
		$this->_obj_id = $data['id'];
	}
	
	public function load_dirty($data)
	{
		$this->_dirty_values = array_merge($this->_dirty_values, $data);
	}
	
	public function get_data()
	{
		return array_merge($this->_values, $this->_dirty_values);
	}
	
	protected static function make_criteria($criteria, $prefix='')
	{
		if (empty($criteria)) {
			return '';
		}
		
		$where = array();
		
		foreach ($criteria as $key => $value) {
			if (is_array($value)) {
				$str = $prefix.$key.' IN(';
				foreach ($value as $val) {
					$str .= self::quote($val).', ';
				}
				$where[] = substr($str, 0, -2).')';
			} else {
				$where[] = ($key == 'raw_sql') ? $value : $prefix.$key.'='.self::quote($value);
			}
		}
		
		return 'WHERE '.implode(' AND ', $where);
	}
	
	public static function find($criteria, $limit=false)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria, static::$_find_prefix), static::$_find_query);
		} else {
			$sql = 'SELECT * FROM '.static::$table.' '.static::make_criteria($criteria);
		}
		
		if ($limit) { 
			$sql .= ' LIMIT '.$limit;
		}

		$rows = self::db()->get_rows($sql);
		
		if (!$rows) {
			return false;
		}
		
		$classname = get_called_class();
		$outp = array();
		foreach ($rows as $row) {
			
			$obj = new $classname();
			$obj->load($row);
			$outp[] = $obj;
		}
		
		return $outp;
	}
	
	protected static function find_one($criteria)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria, static::$_find_prefix), static::$_find_query);
		} else {
			$sql = 'SELECT * FROM '.static::$table.' '.static::make_criteria($criteria);
		}
		$sql .= ' LIMIT 1';
		
		return self::db()->get_row($sql);
	}
	
	public static function find_all()
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', '', static::$_find_query);
		} else {
			$sql = 'SELECT * FROM '.static::$table;
		}
		$rows = self::db()->get_rows($sql);
		
		$classname = get_called_class();
		$outp = array();
		foreach ($rows as $row) {
			
			$obj = new $classname();
			$obj->load($row);
			$outp[] = $obj;
		}
		
		return $outp;
	}
	
	protected static function quote($value)
	{
		return self::db()->quote($value);
	}
	
	protected function quote_date($value)
	{
		if (is_numeric($value)) {
			$value = date('Y-m-d H:i:s', $value);
		}
		
		return self::quote($value);
	}
	
	public function save()
	{
		if (empty($this->_dirty_values)) {
			return;
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if ($this->_obj_id) {
			// update
			$sql = self::db()->make_update(static::$table, $this->_dirty_values, array('id'=>$this->_obj_id));
			
			self::db()->update($sql);
		} else {
			// insert
			$sql = self::db()->make_insert(static::$table, $this->_dirty_values);
			
			$insert_id = self::db()->insert($sql);
			$this->_obj_id = ($this->_dirty_values['id']) ?: $insert_id;
		}
		
		$this->_dirty_values = array();
		
		// refresh the data next time we need to access it
		$this->_loaded = false;
	}
	
	public function delete()
	{
		if (!$this->_loaded) {
			$this->load();
		}
		
		$sql = 'DELETE FROM '.static::$table.' WHERE id="'.$this->_obj_id.'"';
		self::db()->delete($sql);
	}
	
	public function valid()
	{
		if (!$this->_loaded) {
			$this->load();
		}
	
		if ($this->_obj_id) {
			return true;
		} else {
			return false;
		}
	}
	
}