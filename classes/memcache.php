<?php

namespace jmvc\classes;

class Memcache extends Cache_Interface {

	protected static $CONFIG_KEY = 'memcached';

	protected function __construct()
	{
		$config = $GLOBALS['_CONFIG']['memcached'];
		$this->m = new \Memcache();

		$rc = $this->m->addServer($config['host'], $config['port']);

		if (!$rc) {
			throw new \Exception('Error connecting to memcached server at '.$config['host'].' port '.$config['port']);
		}

		self::$stats = array('hits'=>0, 'misses'=>0, 'writes'=>0, 'keys'=>array());
	}

	public static function get_class_name()
	{
		return __CLASS__;
	}

	public function delete($key)
	{
		return $this->m->delete($key, 0);
	}

	public function get($key, &$result, $nobust=false)
	{
		if (defined('BUST_CACHE') && !$nobust) {
			$this->m->delete($key);
			self::$stats['misses']++;
			self::$stats['keys'][] = array($key, 'miss');
			return false;
		}

		$data = $this->m->get($key);

		if ($data === false) {
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

		return $this->m->set($key, $val, 0, $expires);
	}
}
