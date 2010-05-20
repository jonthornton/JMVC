<?php

namespace jmvc;

use jmvc\classes\File_Cache;
use jmvc\classes\Session;

class View {

	protected static $data;
	protected static $cacheme = array();
	
	protected static $site_stack = array();
	protected static $template_stack = array();
	
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
	
	public static function bust_cache($controller_name, $view_name, $args, $site, $template)
	{
		$key = md5(serialize(array($controller_name, $view_name, $args, $site, $template)));
		File_cache::bust($key);
		File_cache::get($key.'meta');
	}

	public static function render($controller_name, $view_name, $args=array(), $cache=null, $site_name=false, $template_name=false)
	{

		if ($site_name) {
			array_unshift(self::$site_stack, $site_name);
		}
		$site = self::$site_stack[0];
		
		if ($template_name) {
			array_unshift(self::$template_stack, $template_name);
		}
		$template = self::$template_stack[0];
	
		$controller = false;
		$view = false;
		
		if ($cache !== null) {
			$key = md5(serialize(array($controller_name, $view_name, $args, $site, $template)));
			
			$output = File_cache::get($key, $cache);
			$meta = File_cache::get($key.'meta', $cache);
			
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
			if ($controller->controller_override) {
				$controller_name = $controller->controller_override;
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
	
		if (!$controller && !$file) {
			pd('abc');
			\JMVC::do404();
		}
		
		if ($site_name) array_shift(self::$site_stack);
		if ($tempate_name) array_shift(self::$template_stack);
		
		if ($cache !== null) {
			File_cache::set($key, $output);
			File_cache::set($key.'meta', serialize(self::$cacheme[$key]));
			
			unset(self::$cacheme[$key]);
		}
		
		return $output;
	}

	public static function render_static($controller_name, $view_name, $args=array(), $site_name=false, $template_name=false)
	{
		if ($site_name) {
			array_unshift(self::$site_stack, $site_name);
		}
		$site = self::$site_stack[0];
		
		if ($template_name) {
			array_unshift(self::$template_stack, $template_name);
		}
		$template = self::$template_stack[0];
		
		if ($file = self::exists($site, $template, $controller_name, $view_name)) {
			if ($controller) extract(get_object_vars($controller));
			
			ob_start();
			include($file);
			$output = ob_get_clean();
		} else {
			\JMVC::do404();
		}
		
		if ($site_name) array_shift(self::$site_stack);
		if ($tempate_name) array_shift(self::$template_stack);
		
		return $output;
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
			if (!isset(Session::$d['flash'])) {
				Session::$d['flash'] = array();
			}
			Session::$d['flash'][] = $msg;
			return;
		}
		
		if (!empty(Session::$d['flash'])) {
			$out = '';
			foreach (Session::$d['flash'] as $msg) {
				$out .= '<div class="msg">'.$msg.'</div>';
			}
			unset(Session::$d['flash']);
			return $out;
		}
	}
}