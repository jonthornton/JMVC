<?php

namespace jmvc\classes;

interface Cache_Interface {
	public static function instance();
	public function delete($key);
	public function get($key, &$result, $nobust=false);
	public function set($key, $val, $expires=0);
}