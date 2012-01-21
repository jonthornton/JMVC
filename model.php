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

abstract class Model {

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

	/**
	 * Create a new object. If an ID or criteria array is passed, populate the object with data from MySQL
	 * @param mixed $id
	 * @return model subclass
	 */
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

	/**
	 * Retrieve an object from MySQL. If the object is not found, return null. Takes the same arguments as __construct()
	 * @param mixed $id
	 * @return model subclass
	 */
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
			return null;
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

	/**
	 * Set an object parameter to an SQL function. $value will go unquoted, leaving
	 * all SQL injection protection to the caller. Useful for setting things
	 * to NOW(), for example
	 * @param type $key
	 * @param type $value
	 * @return void
	 */
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

	/**
	 * Retrieve an instance of the database
	 * @return \jmvc\Db
	 */
	protected static function db()
	{
		if (!self::$db) {
			self::$db = Db::instance();
		}

		return self::$db;
	}

	/**
	 * Retrieve an instance of the cache driver
	 * @return \jmvc\classes\Cache_interface
	 */
	protected static function cache()
	{
		if (!self::$cache) {
			if (isset($GLOBALS['_CONFIG']['cache_driver'])) {
				$class = $GLOBALS['_CONFIG']['cache_driver'];
				self::$cache = $class::instance();
			} else {
				self::$cache = \jmvc\classes\Memcache_Stub::instance();
			}
		}

		return self::$cache;
	}

	/**
	 * Populate the object. If an array is passed, populate the object with passed data.
	 * Otherwise, retrieve data from MySQL.
	 * @param array $data
	 * @return void
	 */
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

	/**
	 * Load an array of data to be saved. Array keys should match the table columns.
	 * @param array $data
	 * @return void
	 */
	public function load_dirty($data)
	{
		$this->_dirty_values = array_merge($this->_dirty_values, $data);
	}

	/**
	 * Retrieve the object data in array form
	 * @return array
	 */
	public function get_data()
	{
		if (!$this->_loaded) {
			$this->load();
		}

		return array_merge($this->_values, $this->_dirty_values);
	}

	/**
	 * Generate SQL for WHERE/HAVING based on passed criteria array
	 * @param array $criteria
	 * @return string
	 */
	protected static function make_criteria($criteria)
	{
		$sql = self::make_where($criteria);

		if (static::$_group_by) {
			$sql .= ' GROUP BY '.static::$_group_by;

			if (isset($criteria['having']) && !empty($criteria['having'])) {
				$sql .= ' HAVING '.implode(' AND ', $criteria['having']);
			}
		}

		return $sql;
	}

	/**
	 * Generate SQL for WHERE based on passed criteria array
	 * @param array $criteria
	 * @return string
	 */
	protected static function make_where($criteria)
	{
		if (empty($criteria)) {
			return '';
		}

		$where = array();
		foreach ($criteria as $key => $value) {

			if ($key == 'having') { //non-where argument passed with criteria
				continue;
			} else {
				$where[] = self::db()->make_parameter($key, $value, static::$_find_prefix);
			}
		}

		if (!empty($where)) {
			return 'WHERE '.implode(' AND ', $where);
		}
	}

	/**
	 * Retrieve an group of objects based on the passed criteria.
	 * @param array $criteria Array keys match table columns. Arguments will be ANDed. Use MySQL dot notation
	 * 						to access columns in other tables. Set key 'raw_sql' to an array to add unquoted
	 * 						arguments to the query.
	 * @param mixed $limit May be null, an integer, or array in the form { page: int, page_size: int}.
	 * @param string $order This is unquoted SQL. It's up to the caller to protect against SQL injection
	 * @param bool $keyed Return an array with object IDs as array keys
	 * @return array of model subclass
	 */
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
			return null;
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

	/**
	 * Get a row count for a particular criteria
	 * @param array $criteria
	 * @return int
	 */
	public static function find_count($criteria)
	{
		if (static::$_group_by) {
			$group = static::$_group_by;
			static::$_group_by = null;
		}

		if (isset(static::$_count_query)) {
			$sql = str_replace('[[WHERE]]', static::make_criteria($criteria), static::$_count_query);
		} else {
			$sql = 'SELECT COUNT(*) FROM '.static::$_table.' '.static::make_criteria($criteria);
		}

		if ($group) {
			static::$_group_by = $group;
		}

		return self::db()->get_row($sql);
	}

	/**
	 * Retrieve the data array for 1 object from MySQL
	 * @param array $criteria
	 * @return array
	 */
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

	/**
	 * Return an array of all obbjects in MySQL
	 * @param string $order This is unquoted SQL. It is up to the caller to protected agains SQL injection.
	 * @return array of model subclass
	 */
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

		if (!$rows) return null;

		$classname = get_called_class();
		$outp = array();
		foreach ($rows as $row) {

			$obj = new $classname();
			$obj->load($row);
			$outp[] = $obj;
		}

		return $outp;
	}

	/**
	 * Get the total number of objects in the database.
	 * @return int
	 */
	public static function find_all_count()
	{
		return self::db()->get_row('SELECT COUNT(*) FROM '.static::$_table);
	}

	/**
	 * MySQL quote a value to protect against SQL injection. Wraps \jmvc\Db::quote().
	 * @param string $value
	 * @return string
	 */
	protected static function quote($value)
	{
		return self::db()->quote($value);
	}

	/**
	 * MySQL quote a datetime. Wraps \jmvc\Db::quote_date().
	 * @param string $value
	 * @return int
	 */
	protected static function quote_date($value)
	{
		return self::db()->quote_date($value);
	}

	/**
	 * Check if the object has data that has not been saved to MySQL
	 * @return bool
	 */
	public function is_dirty()
	{
		return (!empty($this->_dirty_values));
	}

	/**
	 * Save object to MySQL
	 * @param bool $reload Reload the object data after saving. May be necessary to
	 * 						get correct join or MySQL generated data, but causes extra
	 * 						DB hit. Defaults to true.
	 * @return void
	 */
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

	/**
	 * Delete the object from MySQL
	 * @return void
	 */
	public function delete()
	{
		$sql = 'DELETE FROM '.static::$_table.' WHERE id="'.$this->_obj_id.'"';
		self::db()->delete($sql);
	}

	/**
	 * Check if the object corresponds to a MySQL row.
	 * @return bool
	 */
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
