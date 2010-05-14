<?php

namespace jmvc\classes;

class File_Cache {
	
	private function __construct()
	{
	}
	
	public static function get($key, $expires=0)
	{
		if (!IS_PRODUCTION || (defined('BUST_CACHE') && BUST_CACHE)) {
			return false;
		}
		
		$file = CACHE_DIR.'/'.$key.'.'.$_SERVER['HTTP_HOST'].'.cache';
		
		if (!file_exists($file)) {
			return false;
		}
		
		$fp = fopen($file, 'r');
		if (!$fp) {
			return false;
		}
		
		if ($expires) {
			$stat = fstat($fp);
			if ($stat['mtime'] + $expires < time()) {
				return false;
			}
		}
		
		$content = fread($fp, $stat['size']);
		fclose($fp);
		
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
	}
}
?>