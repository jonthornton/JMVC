<?php

namespace jmvc\classes;

class Session {

	public static $d = false;
	protected static $checksum;
	protected static $sessionModel = false;
	protected static $memcached = false;

	const COOKIE_NAME = 'JMVC_SESSION';

	private function __construct()
	{
		// static-only class
	}

	public static function start()
	{

		if ($key = self::id()) {
			if ($GLOBALS['_CONFIG']['session_driver'] == 'memcached') {
				self::$memcached = \jmvc\classes\Memcache::instance();

				self::$memcached->get($key, $data, true);
				self::$d = $data;

			} else {
				self::$sessionModel = \jmvc\models\Session::factory($key);

				if (self::$sessionModel) {
					self::$d = (self::$sessionModel->data) ? unserialize(self::$sessionModel->data) : array();
				} else {
					self::$sessionModel = new \jmvc\models\Session();
				}
			}
		}

		if (!is_array(self::$d)) {
			self::$d = array();
		}

		self::$checksum = md5(serialize(self::$d));
		register_shutdown_function(array('jmvc\classes\Session', 'end'));
	}

	public static function id()
	{
		if (isset($_COOKIE[self::COOKIE_NAME])) {
			return $_COOKIE[self::COOKIE_NAME];
		} else if (isset($_POST[self::COOKIE_NAME])) {
			return $_POST[self::COOKIE_NAME];
		}
	}

	protected static function generate_id()
	{
		$key = 'sesh'.substr(md5($_SERVER['REMOTE_ADDR'].time()), 0, 28);

		setcookie(self::COOKIE_NAME, $key, 0, '/');
		$_COOKIE[self::COOKIE_NAME] = $key;

		return $key;
	}

	public static function end()
	{
		if (self::$checksum == md5(serialize(self::$d))) {
			return;
		}

		if (empty(self::$d)) {
			// session is empty; we can abandon it
			setcookie(self::COOKIE_NAME, '', time()-3600, '/');
			return;
		}

		if (!self::$sessionModel && !self::$memcached) {
			if ($GLOBALS['_CONFIG']['session_driver'] == 'memcached') {
				self::$memcached = \jmvc\classes\Memcache::instance();
			} else {
				self::$sessionModel = new \jmvc\models\Session();
			}
		}

		$key = self::id() ?: self::generate_id();

		if (self::$memcached) {
			self::$memcached->set($key, self::$d, ONE_DAY);
		} else if (self::$sessionModel) {
			self::$sessionModel->id = $key;
			self::$sessionModel->data = serialize(self::$d);
			self::$sessionModel->save();
		}
	}
}
