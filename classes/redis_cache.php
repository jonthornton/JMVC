<?php

namespace jmvc\classes;

class Redis_Cache extends Cache_Interface {

	protected static $CONFIG_KEY = 'redis';

	protected function __construct()
	{
		$config = $GLOBALS['_CONFIG']['redis'];
		$this->m = new \Redis();

		$rc = $this->m->connect($config['host'], $config['port']);

		if (!$rc) {
			throw new \Exception('Error connecting to redis server at '.$config['host'].' port '.$config['port']);
		}

		self::$stats = array('hits'=>0, 'misses'=>0, 'writes'=>0, 'keys'=>array());
	}

	public static function get_class_name()
	{
		return __CLASS__;
	}

	public function delete($key)
	{
		return $this->m->delete(self::PREFIX.$key);
	}

	public function get($key, &$result, $nobust=false)
	{
		if (BUST_CACHE && !$nobust) {
			$this->delete($key);
			self::$stats['misses']++;
			self::$stats['keys'][] = array($key, 'miss');
			return false;
		}

		$data = false;
		// check the ttl to prevent dogpiling
		if ($ttl = $this->m->ttl(self::PREFIX.$key)) {
			if ($ttl < self::EXPIRES_PADDING) {
				$this->m->expire($key, ONE_HOUR);
			} else {
				$data = $this->m->get(self::PREFIX.$key);
			}
		}

		if ($data === false) {
			self::$stats['misses']++;
			self::$stats['keys'][] = array($key, 'miss');
			return false;
		} else {
			self::$stats['hits']++;
			self::$stats['keys'][] = array($key, 'hit');
			$result = self::defalsify(unserialize($data));
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

		return $this->m->setex(self::PREFIX.$key, $expires+self::EXPIRES_PADDING, serialize($val));
	}
}
