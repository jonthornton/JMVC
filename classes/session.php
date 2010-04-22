<?php

namespace jmvc\classes;
use jmvc\models\Session as Session_M;

class Session {

	public static $d;
	protected static $old_d;
	protected static $savedSession = false;
	
	const COOKIE_NAME = 'JMVC_SESSION';
	
	private function __construct()
	{
		// static-only class
	}
	
	public function start()
	{
		if (isset($_COOKIE[self::COOKIE_NAME])) {
			self::$savedSession = new Session_M($_COOKIE[self::COOKIE_NAME]);
			
			if (!self::$savedSession->id) {
				self::$savedSession = false;
			}
			
			self::$d = (self::$savedSession) ? unserialize(self::$savedSession->data) : array();
		} else {
			self::$d = array();
		}
		
		self::$old_d = self::$d;
		register_shutdown_function(array('jmvc\classes\Session', 'end'));
	}
	
	public function end()
	{
		if (self::$old_d == self::$d) {
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