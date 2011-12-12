<?php

namespace jmvc\classes;

abstract class Cache_Interface {

	protected $m;
    protected static $instance;

	public static $stats;

	const FALSE = '^%$@FSDerwo';
	const ZERO = '^%$@Fkdjrwo';
	const PREFIX = 'rc:';
	const EXPIRES_PADDING = 10;


	public static function instance()
	{
		if (!isset(self::$instance)) {
			if (isset($GLOBALS['_CONFIG'][static::$CONFIG_KEY])) {
				$c = static::get_class_name();

				self::$instance = new $c;
			} else {
				self::$instance = Memcache_Stub::instance();
			}
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

	abstract public function delete($key);
	abstract public function get($key, &$result, $nobust=false);
	abstract public function set($key, $val, $expires=0);
	abstract public static function get_class_name();
}
