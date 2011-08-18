<?php

namespace jmvc;

class DataException extends \Exception {

	protected $field;
	
	public function __construct($field, $message)
	{
		$this->field = $field;
		
		parent::__construct($message);
	}
	
	public function getField()
	{
		return $this->field;
	}
}

class Model {
	
	protected $_values = array();
	protected $_dirty_values = array();
	protected $_loaded = false;
	protected $_criteria = false;
	protected $_obj_id = false;
	
	private static $db;
	private static $cache;
	protected static $obj_cache = array();
	protected static $obj_cache_count = array();
	
	
	protected static $_table;
	protected static $_find_query;
	protected static $_find_prefix = '';
	protected static $_find_order = null;
	protected static $_field_types = array();
	protected static $_group_by = false;
	
	public function __construct($id=false)
	{
		if ($id) {
			if (is_array($id)) {
				$this->_criteria = $id;
			} else {
				$this->_criteria = array('id'=>$id);
			}
			
			if (is_numeric($id)) {
				$this->_obj_id = $id;
			}
		}
	}
	
	public static function factory($id=false)
	{
		if (is_numeric($id) && isset(self::$obj_cache[static::$_table][$id])) {
			return self::$obj_cache[static::$_table][$id];
		}
		
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
		
		if (isset(static::$_field_types[$key]) && static::$_field_types[$key] == 'array' && !is_array($data)) {
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
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if (array_key_exists($key, $this->_values)) {
			return $this->_values[$key];
		}
		
		return null;
	}
	
	public function id()
	{
		if (isset($this->_obj_id)) {
			return $this->_obj_id;
		} else {
			throw new \Exception('Invalid acces to _obj_id!');
		}
	}
	
	public function __set($key, $value)
	{
		if ($key[0] == '_') {
			$this->$key = $value;
			return;
		}
		
		if (!$this->_loaded) {
			$this->load();
		}
		
		if (!isset($this->_values[$key]) && $value !== NULL) {
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
		if (!self::$db) {
			self::$db = Db::instance();
		}
		
		return self::$db;
	}
	
	protected static function cache()
	{
		if (!self::$cache) {
			self::$cache = \jmvc\classes\Memcache::instance();
		}
		
		return self::$cache;
	}
	
	public function load($data=false)
	{
		if ($this->_obj_id) $this->_criteria = array('id'=>$this->_obj_id);
		if (!$data && $this->_criteria) {
			$data = self::find_one($this->_criteria);
		}
		
		$this->_loaded = true;
		
		if (!$data) {
			if ($this->_obj_id) {
				$this->_values = array();
				$this->_obj_id = null;
				self::$obj_cache[static::$_table][$this->_obj_id] = null;
			}
			return false;
		}
		
		$this->_values = $data;
		$this->_obj_id = $data['id'];
		
		if (self::$obj_cache_count[static::$_table]) {
			self::$obj_cache[static::$_table] = array();
		}
		
		self::$obj_cache[static::$_table][$this->_obj_id] = $this;
		self::$obj_cache_count[static::$_table]++;
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
	
	protected static function make_criteria($criteria)
	{
		if (!empty($criteria)) {
		
			$where = array();
			foreach ($criteria as $key => $value) {
			
				if ($key == 'raw_sql') {
					// Raw (non-quoted) SQL
					
					if (is_array($value)) $value = implode(' AND ', $value);
					
					$where[] = $value;
				} else if ($key == 'having') {
					// Raw (non-quoted) SQL
					$having = $value;
				} else if (is_array($value)) {
				
					if (empty($value)) {
						$where[] = static::$_find_prefix.$key.' IS NULL';
					} else {
						// IN criteria
						$str = static::$_find_prefix.$key.' IN(';
						foreach ($value as $val) {
							$str .= self::quote($val).', ';
						}
						$where[] = substr($str, 0, -2).')';
					}
				} else if ($value === NULL) {
					// NULL
					$where[] = static::$_find_prefix.$key.' IS NULL';
				} else if (substr($key, -7) == '_before') {
					// DateTime range
					$where[] = static::$_find_prefix.substr($key, 0, -7).' < '.self::quote_date($value);
				} else if (substr($key, -6) == '_after') {
					// DateTime range
					$where[] = static::$_find_prefix.substr($key, 0, -6).' >= '.self::quote_date($value);
				} else {
					// equals
					$where[] = static::$_find_prefix.$key.'='.self::quote($value);
				}
			}
		}
		
		$sql = '';
		
		if (!empty($where)) {
			$sql .= 'WHERE '.implode(' AND ', $where);
		}
		
		if (static::$_group_by) {
			$sql .= ' GROUP BY '.static::$_group_by;
			
			if (!empty($having)) {
				$sql .= ' HAVING '.implode(' AND ', $having);
			}
		}
		
		return $sql;
	}
	
	public static function find($criteria, $limit=false, $order=false, $keyed=false)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria), static::$_find_query);
			
			if ($order) {
				$sql .= ' ORDER BY '.$order;
			} else if (static::$_find_order) {
				$sql .= ' ORDER BY '.static::$_find_order;
			}
		} else {
			$sql = 'SELECT * FROM '.static::$_table.' '.static::make_criteria($criteria).' ORDER BY id';
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
	
	public static function find_count($criteria)
	{
		if (static::$_group_by) {
			$group = static::$_group_by;
			static::$_group_by = null;
		}
		
		if (static::$_count_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria), static::$_count_query);
		} else {
			$sql = 'SELECT COUNT(*) FROM '.static::$_table.' '.static::make_criteria($criteria);
		}
		
		if ($group) {
			static::$_group_by = $group;
		}
		
		return self::db()->get_row($sql);
	}
	
	protected static function find_one($criteria)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria), static::$_find_query);
		} else {
			$sql = 'SELECT * FROM '.static::$_table.' '.static::make_criteria($criteria);
		}
		$sql .= ' LIMIT 1';
		
		return self::db()->get_row($sql);
	}
	
	public static function find_all($order=false)
	{
		if (static::$_find_query) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria), static::$_find_query);
			
			if ($order) {
				$sql .= ' ORDER BY '.$order;
			} else if (static::$_find_order) {
				$sql .= ' ORDER BY '.static::$_find_order;
			}
		} else {
			$sql = 'SELECT * FROM '.static::$_table.' ORDER BY id';
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
		return self::db()->get_row('SELECT COUNT(*) FROM '.static::$_table);
	}
	
	protected static function quote($value)
	{
		return self::db()->quote($value);
	}
	
	protected static function quote_date($value)
	{
		return self::db()->quote_date($value);
	}
	
	public function is_dirty()
	{
		return (!empty($this->_dirty_values));
	}
	
	public function save($reload=true)
	{
		if (!$this->is_dirty()) {
			return;
		}
		
		foreach (array_keys($this->_dirty_values) as $key) {
			if (static::$_field_types[$key] == 'array' && is_array($this->_dirty_values[$key])) {
				$this->_dirty_values[$key] = serialize($this->_dirty_values[$key]);
			}
		}
		
		if ($this->_obj_id) {
			// update
			$sql = self::db()->make_update(static::$_table, $this->_dirty_values, array('id'=>$this->_obj_id), static::$_field_types);
			
			self::db()->update($sql);
		} else {
			// insert
			$sql = self::db()->make_insert(static::$_table, $this->_dirty_values, static::$_field_types);
			
			$insert_id = self::db()->insert($sql);
			$this->_obj_id = ($this->_dirty_values['id']) ?: $insert_id;
		}
		
		if ($reload) {
			// refresh the data next time we need to access it
			$this->_loaded = false;
		} else {
			array_merge($this->_values, $this->_dirty_values);
		}
		
		$this->_dirty_values = array();
	}
	
	public function delete()
	{
		$sql = 'DELETE FROM '.static::$_table.' WHERE id="'.$this->_obj_id.'"';
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