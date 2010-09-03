<?php

namespace jmvc\classes;
use jmvc\models\Session as Session_M;

class Session {

	public static $d;
	protected static $checksum;
	protected static $savedSession = false;
	
	const COOKIE_NAME = 'JMVC_SESSION';
	
	private function __construct()
	{
		// static-only class
	}
	
	public static function start()
	{
		$key = self::id();
		if ($key) {
			self::$savedSession = new Session_M($key);
			
			if (!self::$savedSession->id) {
				self::$savedSession = false;
			}
			
			self::$d = (self::$savedSession) ? unserialize(self::$savedSession->data) : array();
		} else {
			self::$d = array();
		}
		
		self::$checksum = md5(serialize(self::$d));
		register_shutdown_function(array('jmvc\classes\Session', 'end'));
	}
	
	public static function id()
	{
		return $_COOKIE[self::COOKIE_NAME] ?: $_POST[self::COOKIE_NAME];
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
		
		if (!self::$savedSession) {
			$key = md5($_SERVER['REMOTE_ADDR'].time());
			
			setcookie(self::COOKIE_NAME, $key, 0, '/');
			$_COOKIE[self::COOKIE_NAME] = $key;
			
			self::$savedSession = new Session_M();
			self::$savedSession->id = $key;
		}
		
		self::$savedSession->data = serialize(self::$d);
		self::$savedSession->save();
	}
}