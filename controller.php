<?php

namespace jmvc;

class Controller {

	public function __construct($args)
	{
		$this->args = $args;
	}
	
	public function route_object($model, $default=false, &$obj=null)
	{
		if (!is_numeric($this->args[0])) {
			return false;
		}
		
		$method = $this->args[1] ?: $default;
		if (!method_exists($this, $method)) {
			\jmvc::do404();
		}
		
		$obj = $model::factory($this->args[0]);
		if (!$obj) {
			\jmvc::do404();
		}
		
		$this->view_override = $method;
		$this->$method($obj);
		return true;
	}
	
	public static function forward($url, $permanent=false)
	{
		if ($permanent) {
			header("HTTP/1.1 301 Moved Permanently");
		}
		
		header('location: '.$url);
		exit();
	}
	
	public static function get($site, $controller, $args=array())
	{
		if (!self::exists($site, $controller)) {
			return false;
		}
		
		$controller_name = 'controllers\\'.$site.'\\'.$controller;
		
		return new $controller_name($args);
	}

	public static function exists($site, $controller)
	{
		return file_exists(APP_DIR.'sites/'.$site.'/'.strtolower($controller).'.php');
	}
	
	public static function flash($msg, $bucket=0)
	{
		View::flash($msg, $bucket);
	}
}