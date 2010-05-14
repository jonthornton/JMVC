<?php

namespace jmvc;

class View {

	protected static $data;
	protected static $cacheme = array();
	
	public static function get($key)
	{
		return self::$data[$key];
	}
	
	public static function push($key, $val, $unique=false)
	{
		if (!empty(self::$cacheme)) {
			foreach (self::$cacheme as $key=>$data) {
				self::$cacheme[$key]['push'][] = func_get_args();
			}
		}
		
		if (!is_array(self::$data[$key])) {
			self::$data[$key] = array();
		}
		
		if (!$unique || !in_array($val, self::$data[$key])) {
			self::$data[$key][] = $val;
		}
	}
	
	public static function set($key, $val)
	{
		if (!empty(self::$cacheme)) {
			foreach (self::$cacheme as $key=>$data) {
				self::$cacheme[$key]['set'][] = func_get_args();
			}
		}
		
		if (!isset(self::$data[$key])) {
			self::$data[$key] = $val;
		}
	}

	public static function render($controller_name, $view_name, $args=array(), $cache=null, $site=SITE, $template=TEMPLATE)
	{
		$controller = false;
		$view = false;
		
		if ($cache !== null) {
			$key = md5(serialize(func_get_args()));
			
			$output = \jmvc\classes\file_cache::get($key, $cache);
			$meta = \jmvc\classes\file_cache::get($key.'meta', $cache);
			
			if ($meta) {
				$meta = unserialize($meta);
				foreach ($meta['push'] as $push) {
					self::push($push[0], $push[1], $push[2]);
				}
				foreach ($meta['set'] as $set) {
					self::push($set[0], $set[1]);
				}
			}
			
			if ($output) {
				return $output;
			}
			
			self::$cacheme[$key] = array('set'=>array(), 'push'=>array());
		}
		
		$controller = Controller::get($site, $controller_name, $args);
		if ($controller && method_exists($controller, $view_name)) {
			$controller->$view_name();
			
			if ($controller->view_override) {
				$view_name = $controller->view_override;
			}
			
		} else {
			$controller = false;
		}
		
		if ($file = self::exists($site, $template, $controller_name, $view_name)) {
			if ($controller) extract(get_object_vars($controller));
			
			ob_start();
			include($file);
			$output = ob_get_clean();
		}
	
		if (!$controller && !$output) {
			\JMVC::do404();
		}
		
		if ($cache !== null) {
			\jmvc\classes\file_cache::set($key, $output);
			\jmvc\classes\file_cache::set($key.'meta', serialize(self::$cacheme[$key]));
			
			unset(self::$cacheme[$key]);
		}
		
		return $output;
	}

	public static function render_static($controller_name, $view_name, $args=array(), $site=SITE, $template=TEMPLATE)
	{
		if ($file = self::exists($site, $template, $controller_name, $view_name)) {
			if ($controller) extract(get_object_vars($controller));
			
			ob_start();
			include($file);
			$output = ob_get_clean();
			return $output;
		} else {
			\JMVC::do404();
		}
	}
	
	public static function exists($site, $template, $controller, $view, $check_controller=false)
	{
		$file = APP_DIR.'sites/'.$site.'/'.$template.'/'.$controller.'.'.$view.'.php';
		if (file_exists($file)) {
			return $file;
		}
		
		$file = APP_DIR.'sites/'.$site.'/html/'.$controller.'.'.$view.'.php';
		if (file_exists($file)) {
			return $file;
		}
		
		if ($check_controller && Controller::exists($site, $controller) && method_exists('controllers\\'.$site.'\\'.$controller, $view)) {
			return true;
		} else {
			return false;
		}
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