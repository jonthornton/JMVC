<?php

namespace jmvc\classes;

class Memcache_Stub extends Cache_Interface {

	protected static $data;

	private function __construct()
	{
		self::$data;
		self::$stats = array('hits'=>0, 'misses'=>0, 'writes'=>0, 'keys'=>array());
	}

	public static function instance()
	{
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public static function get_class_name()
	{
		return __CLASS__;
	}

	public function delete($key)
	{
		unset(self::$data[$key]);
	}

	public function get($key, &$result, $nobust=false)
	{
		if (BUST_CACHE && !$nobust) {
			return false;
		}

		$data = self::$data[$key];

		if ($data === null) {
			self::$stats['misses']++;
			self::$stats['keys'][] = array($key, 'miss');
			return false;
		} else {
			self::$stats['hits']++;
			self::$stats['keys'][] = array($key, 'hit');
			$result = self::defalsify($data);
			return true;
		}
	}

	public function set($key, $val, $expires=0)
	{
		self::$stats['writes']++;
		self::$stats['keys'][] = array($key, 'write');

		if (!is_array($val)) {
			$val = self::falsify($val);
		}

		self::$data[$key] = $val;
	}
}
