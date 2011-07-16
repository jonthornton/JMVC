<?php

namespace jmvc\classes;

class Memcache_Stub implements Cache_Interface {
	
	protected static $instance;
    protected static $data;
	
	public static $stats;
	
	const FALSE = '^%$@FSDerwo';
	const ZERO = '^%$@Fkdjrwo';
	
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
	
	protected static function falsify($data)
	{
		if (is_array($data)) {
			foreach ($data as $key=>$val) {
				if ($val === 0) {
					$data[$key] = self::ZERO;
				} else if (!$val) {
					$data[$key] = self::FALSE;
				}
			}
		} else {
			if ($data === 0) {
				$data = self::ZERO;
			} else if (!$data) {
				$data = self::FALSE;
			}
		}
		
		return $data;
	}
	
	protected static function defalsify($data)
	{
		if (is_array($data)) {
			foreach ($data as $key=>$val) {
				if ($val === self::ZERO) {
					$data[$key] = 0;
				} else if ($val === self::FALSE) {
					$data[$key] = FALSE;
				}
			}
		} else {
			if ($data === self::ZERO) {
				$data = 0;
			} else if ($data === self::FALSE) {
				$data = FALSE;
			}
		}
		
		return $data;
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