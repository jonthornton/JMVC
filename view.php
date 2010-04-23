<?php

namespace jmvc;

class View {

	protected static $data;
	
	public static function get($key)
	{
		return self::$data[$key];
	}
	
	public static function push($key, $value, $unique=false)
	{
		if (!is_array(self::$data[$key])) {
			self::$data[$key] = array();
		}
		
		if (!$unique || !in_array($val, self::$data[$key])) {
			self::$data[$key][] = $val;
		}
	}
	
	public static function set($key, $val)
	{
		self::$data[$key] = $val;
	}

	public static function render($controller_name, $view_name, $args=array(), $site=SITE)
	{
		$controller = false;
		$view = false;
		
		$controller = Controller::get($site, $controller_name, $args);
		if ($controller && method_exists($controller, $view_name)) {
			$controller->$view_name();
			
			if ($controller->view_override) {
				$view_name = $controller->view_override;
			}
			
		} else {
			$controller = false;
		}
		
		if (self::exists($site, $controller_name, $view_name)) {
			if ($controller) extract(get_object_vars($controller));
			
			ob_start();
			include(APP_DIR.'sites/'.$site.'/views/'.$controller_name.'.'.$view_name.'.php');
			$output = ob_get_clean();
			
			if ($controller && $controller->no_template) {
				echo $output;
				exit;
			} else {
				return $output;
			}
		}
		
		if (!$controller && !$view) {
			\JMVC::do404();
		}
	}
	
	public static function exists($site, $controller_name, $view_name)
	{
		return file_exists(APP_DIR.'sites/'.$site.'/views/'.$controller_name.'.'.$view_name.'.php');
	}
	
	public static function flash($msg=false)
	{
		if ($msg) {
			if (!isset(\jmvc\classes\Session::$d['flash'])) {
				\jmvc\classes\Session::$d['flash'] = array();
			}
			\jmvc\classes\Session::$d['flash'][] = $msg;
			return;
		}
		
		if (!empty(\jmvc\classes\Session::$d['flash'])) {
			$out = '';
			foreach (\jmvc\classes\Session::$d['flash'] as $msg) {
				$out .= '<div class="msg">'.$msg.'</div>';
			}
			unset(\jmvc\classes\Session::$d['flash']);
			return $out;
		}
	}
}