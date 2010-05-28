<?php

namespace jmvc\classes;

class File_Cache {

	public static $stats = array('hits'=>0, 'misses'=>0, 'writes'=>0);
	
	private function __construct()
	{
	}
	
	public static function bust($key)
	{
		$file = CACHE_DIR.'/'.$key.'.'.$_SERVER['HTTP_HOST'].'.cache';
		if (!file_exists($file)) {
			return;
		}
		
		unlink($file);
	}
	
	public static function get($key, $expires=0)
	{
		if (!IS_PRODUCTION || (defined('BUST_CACHE') && BUST_CACHE)) {
			self::$stats['misses']++;
			return false;
		}
		
		$file = CACHE_DIR.'/'.$key.'.'.$_SERVER['HTTP_HOST'].'.cache';
		
		if (!file_exists($file)) {
			self::$stats['misses']++;
			return false;
		}
		
		$fp = fopen($file, 'r');
		if (!$fp) {
			self::$stats['misses']++;
			return false;
		}
		
		if ($expires) {
			$stat = fstat($fp);
			if ($stat['mtime'] + $expires < time()) {
				self::$stats['misses']++;
				return false;
			}
		}
		
		$content = fread($fp, $stat['size']);
		fclose($fp);
		
		self::$stats['hits']++;
		return $content;
	}
	
	public static function set($key, $text)
	{
		if (!IS_PRODUCTION || empty($text)) {
			return;
		}
		
		$file = CACHE_DIR.'/'.$key.'.'.$_SERVER['HTTP_HOST'].'.cache';
		
		$fp = fopen($file, 'w');
		if (!$fp) {
			return;
		}
		
		fwrite($fp, $text);
		fclose($fp);
		self::$stats['writes']++;
	}
}
?>