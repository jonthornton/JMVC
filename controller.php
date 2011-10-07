<?php

namespace jmvc;

class Controller {

	public function __construct($args, $context=array())
	{
		$this->args = $args;
		$this->context = $context;
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
		
		$this->view_override(array('view'=>$method));
		$this->$method($obj);
		return true;
	}
	
	public function view_override($context=null)
	{
		if (is_array($context)) {
			$this->context_override = $context;
		} else if (is_string($context)) {
			$this->context_override = array('view'=>$context);
		} else {
			return $this->context_override;
		}
	}
	
	public static function forward($url, $permanent=false)
	{
		if ($permanent) {
			header('HTTP/1.1 301 Moved Permanently');
		} else {
			header('HTTP/1.1 303 See Other');
		}
		
		header('location: '.$url);
		exit();
	}
	
	public static function factory($context, $args=array())
	{
		if (!self::exists($context['site'], $context['controller'])) {
			return false;
		}
		
		$controller_name = 'controllers\\'.$context['site'].'\\'.$context['controller'];
		
		return new $controller_name($args, $context);
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