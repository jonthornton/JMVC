<?php

namespace jmvc\classes;

class Memcache_Stub implements Cache_Interface {
	
	protected $m;
    protected static $instance;

	private function __construct()
	{
	}
	
	public static function instance()
	{
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		
		return self::$instance;
	}

	public function delete($key)
	{
		return;
	}
	
	public function get($key, &$result, $nobust=false)
	{
		return false;
	}
	
	public function set($key, $val, $expires=0)
	{
		return;
	}
}