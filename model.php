<?php

namespace jmvc;

class Model {
	
	protected $_values = array();
	protected $_dirty_values = array();
	protected $_meta_values = array();
	protected $_loaded = false;
	protected $_criteria = false;
	protected $_obj_id = false;
	
	protected static $_table;
	protected static $_find_query;
	protected static $_find_prefix = '';
	protected static $_find_order = null;
	protected static $_field_types = array();
	
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
		$data = $this->get_value($key);
		
		if (static::$_field_types[$key] == 'array' && !is_array($data)) {
			if (empty($data)) {
				$this->_values[$key] = array();
			} else {
				$this->_values[$key] = unserialize($data);
				$data = $this->_values[$key];
			}
		}
		
		return $data;
	}
	
	private function get_value($key)
	{
		if (array_key_exists($key, $this->_dirty_values)) {
			return $this->_dirty_values[$key];
		}
		
		if ($key[0] == '_') {
			return $this->_meta_values[$key];
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if (array_key_exists($key, $this->_values)) {
			return $this->_values[$key];
		}
		
		return null;
	}
	
	public function __set($key, $value)
	{
		if ($key[0] == '_') {
			$this->_meta_values[$key] = $value;
			return;
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if (!isset($this->_values[$key])) {
			$this->_dirty_values[$key] = $value;
		} else if (is_numeric($value) && is_numeric($this->$key)) {
			if ($value*1.0 != $this->$key*1.0) {
				$this->_dirty_values[$key] = $value;
			}
		} else if ($value !== $this->$key) {
			$this->_dirty_values[$key] = $value;
		}
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
		if (!$this->_loaded) {
			$this->load();
		}
		
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
				// IN criteria
				$str = $prefix.$key.' IN(';
				foreach ($value as $val) {
					$str .= self::quote($val).', ';
				}
				$where[] = substr($str, 0, -2).')';
			} else if ($value === NULL) {
				// NULL
				$where[] = $prefix.$key.' IS NULL';
			} else if (substr($key, -7) == '_before') {
				// DateTime range
				$where[] = $prefix.substr($key, 0, -7).' < '.self::quote_date($value);
			} else if (substr($key, -6) == '_after') {
				// DateTime range
				$where[] = $prefix.substr($key, 0, -6).' >= '.self::quote_date($value);
			} else if ($key == 'raw_sql') {
				// Raw (non-quoted) SQL
				$where[] = $value;
			} else {
				// equals
				$where[] = $prefix.$key.'='.self::quote($value);
			}
		}
		
		return 'WHERE '.implode(' AND ', $where);
	}
	
	public static function find($criteria, $limit=false, $order=false, $keyed=false)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria, static::$_find_prefix), static::$_find_query);
			
			if ($order) {
				$sql .= ' ORDER BY '.$order;
			} else if (static::$_find_order) {
				$sql .= ' ORDER BY '.static::$_find_order;
			}
		} else {
			$sql = 'SELECT * FROM '.static::$table.' '.static::make_criteria($criteria).' ORDER BY id';
		}
		
		if ($limit) { 
			if (is_array($limit)) {
				$sql .= ' LIMIT '.($limit['page']*$limit['page_size']).', '.$limit['page_size'];
			} else if (is_numeric($limit)) {
				$sql .= ' LIMIT '.$limit;
			}
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
			
			if ($keyed) {
				$outp[$obj->id] = $obj;
			} else {
				$outp[] = $obj;
			}
		}
		
		return $outp;
	}
	
	public static function find_count()
	{
		if (static::$_count_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria, static::$_find_prefix), static::$_count_query);
		} else {
			$sql = 'SELECT COUNT(*) FROM '.static::$table.' '.static::make_criteria($criteria);
		}
		
		return self::db()->get_row($sql);
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
	
	public static function find_all($order=false)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', '', static::$_find_query);
			
			if ($order) {
				$sql .= ' ORDER BY '.$order;
			} else if (static::$_find_order) {
				$sql .= ' ORDER BY '.static::$_find_order;
			}
		} else {
			$sql = 'SELECT * FROM '.static::$table.' ORDER BY id';
		}

		$rows = self::db()->get_rows($sql);
		
		if (!$rows) return false;
		
		$classname = get_called_class();
		$outp = array();
		foreach ($rows as $row) {
			
			$obj = new $classname();
			$obj->load($row);
			$outp[] = $obj;
		}
		
		return $outp;
	}
	
	public static function find_all_count()
	{
		return self::db()->get_row('SELECT COUNT(*) FROM '.static::$table);
	}
	
	protected static function quote($value)
	{
		return self::db()->quote($value);
	}
	
	protected function quote_date($value)
	{
		return self::db()->quote_date($value);
	}
	
	public function save()
	{
		if (empty($this->_dirty_values)) {
			return;
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		foreach (array_keys($this->_dirty_values) as $key) {
			if (static::$_field_types[$key] == 'array') {
				$this->_dirty_values[$key] = serialize($this->_dirty_values[$key]);
			}
		}
		
		if ($this->_obj_id) {
			// update
			$sql = self::db()->make_update(static::$table, $this->_dirty_values, array('id'=>$this->_obj_id), static::$_field_types);
			
			self::db()->update($sql);
		} else {
			// insert
			$sql = self::db()->make_insert(static::$table, $this->_dirty_values, static::$_field_types);
			
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