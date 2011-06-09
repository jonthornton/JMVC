<?php

namespace jmvc\Classes;

class Memcache {
	
	protected $m;
    protected static $instance;
	
	public static $stats;
	
	const FALSE = '^%$@FSDerwo';
	const ZERO = '^%$@Fkdjrwo';
	
	private function __construct()
	{
		$config = $GLOBALS['_CONFIG']['memcached'];
		$this->m = new \Memcache();
		
		$rc = $this->m->addServer($config['host'], $config['port']);
		
		if (!$rc) {
			throw new \Exception('Error connecting to memcached server at '.$config['host'].' port '.$config['port']);
		}
		
		self::$stats = array('hits'=>0, 'misses'=>0, 'writes'=>0);
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
		return $this->m->delete($key);
	}
	
	public function get($key, &$result)
	{
		$data = $this->m->get($key);
		
		if ($data === false) {
			self::$stats['misses']++;
			return false;
		} else {
			self::$stats['hits']++;
			$result = self::defalsify($data);
			return true;
		}
	}
	
	public function set($key, $val, $expires=0)
	{
		self::$stats['writes']++;
		
		if (!is_array($val)) {
			$val = self::falsify($val);
		}
		
		return $this->m->set($key, $val, 0, $expires);
	}
}