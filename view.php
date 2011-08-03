<?php

namespace jmvc;

use jmvc\classes\Session;

class View {

	protected static $data;
	protected static $cacheme = array();
	protected static $site_stack = array();
	protected static $template_stack = array();
	protected static $controller_stack = array();
	protected static $view_stack = array();
	
	private static $cache;
	
	private function __construct()
	{
		// pure static class
	}
	
	public static function cache()
	{
		if (!self::$cache) {
			self::$cache = \jmvc\classes\Memcache::instance();
		}
		
		return self::$cache;
	}
	
	public static function get($key)
	{
		if (isset(self::$data[$key])) {
			return self::$data[$key];
		}
	}
	
	public static function push($key, $val, $unique=false)
	{
		if (!empty(self::$cacheme)) {
			foreach (self::$cacheme as $cache_key=>$data) {
				self::$cacheme[$cache_key]['push'][] = func_get_args();
			}
		}
		
		if (!isset(self::$data[$key]) || !is_array(self::$data[$key])) {
			self::$data[$key] = array();
		}
		
		if (!$unique || !in_array($val, self::$data[$key])) {
			self::$data[$key][] = $val;
		}
	}
	
	public static function set($key, $val)
	{
		if (!empty(self::$cacheme)) {
			foreach (self::$cacheme as $cache_key=>$data) {
				self::$cacheme[$cache_key]['set'][] = func_get_args();
			}
		}
		
		if (!isset(self::$data[$key])) {
			self::$data[$key] = $val;
		}
	}
	
	public static function reset($key, $val)
	{
		if (!empty(self::$cacheme)) {
			foreach (self::$cacheme as $cache_key=>$data) {
				self::$cacheme[$cache_key]['reset'][] = func_get_args();
			}
		}
		
		self::$data[$key] = $val;
	}
	
	public static function bust_cache($controller_name, $view_name, $args, $site, $template)
	{
		$key = md5(serialize(array($controller_name, $view_name, $args, $site, $template)));
		self::cache()->delete($key);
		self::cache()->delete($key.'meta');
	}

	public static function render($controller_name, $view_name, $args=array(), $cache_expires=null, $site_name=false, $template_name=false)
	{
		if (in_array($view_name, array('get', 'route_object', 'forward', 'exists', 'flash'))) {
			\jmvc::do404(false);
		}

		if ($site_name) array_unshift(self::$site_stack, $site_name);
		$site = self::$site_stack[0];
		
		if ($template_name) array_unshift(self::$template_stack, $template_name);
		$template = self::$template_stack[0];
		
		array_unshift(self::$controller_stack, $controller_name);
		array_unshift(self::$view_stack, $view_name);
		
		if (isset(self::$view_stack[1])) $parent['view'] = self::$view_stack[1];
		if (isset(self::$controller_stack[1])) $parent['controller'] = self::$controller_stack[1];
		if (isset(self::$template_stack[1])) $parent['template'] = self::$template_stack[1];
		if (isset(self::$site_stack[1])) $parent['site'] = self::$site_stack[1];
		
		if (!empty($parent)) $args['parent'] = $parent;
	
		$controller = false;
		$view = false;
		
		if ($cache_expires !== null) {
			$key = 'view'.md5(serialize(array($controller_name, $view_name, $args, $site, $template)));
			
			if (self::cache()->get($key.'meta', $meta)) {
				
				foreach ($meta['push'] as $push) {
					self::push($push[0], $push[1], $push[2]);
				}
				foreach ($meta['set'] as $set) {
					self::set($set[0], $set[1]);
				}
				foreach ($meta['reset'] as $reset) {
					self::reset($reset[0], $reset[1]);
				}
			}
			
			if (self::cache()->get($key, $output)) {
				return $output;
			}
			
			self::$cacheme[$key] = array('set'=>array(), 'push'=>array(), 'reset'=>array());
		}
		
		$controller = Controller::get($site, $controller_name, $args+array('site'=>$site, 'template'=>$template));
		if ($controller && method_exists($controller, $view_name)) {
			$controller->$view_name();
			
			if (isset($controller->view_override)) {
				$view_name = $controller->view_override;
			}
			if (isset($controller->controller_override)) {
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
			throw new \ErrorException('Can\'t find view. View: '.$view_name.', Controller: '.$controller_name.', Site: '.$site.', Template: '.$template);
		}
		
		if ($site_name) array_shift(self::$site_stack);
		if ($template_name) array_shift(self::$template_stack);
		array_shift(self::$controller_stack);
		array_shift(self::$view_stack);
		
		if ($cache_expires !== null) {
			self::cache()->set($key, $output, $cache_expires);
			self::cache()->set($key.'meta', self::$cacheme[$key], $cache_expires);
			
			unset(self::$cacheme[$key]);
		}
		
		return $output;
	}

	public static function render_static($controller_name, $view_name, $args=array(), $site_name=false, $template_name=false)
	{
		if ($site_name) array_unshift(self::$site_stack, $site_name);
		$site = self::$site_stack[0];
		
		if ($template_name) array_unshift(self::$template_stack, $template_name);
		$template = self::$template_stack[0];
		
		array_unshift(self::$controller_stack, $controller_name);
		array_unshift(self::$view_stack, $view_name);
		
		if (isset(self::$view_stack[1])) $parent['view'] = self::$view_stack[1];
		if (isset(self::$controller_stack[1])) $parent['controller'] = self::$controller_stack[1];
		if (isset(self::$template_stack[1])) $parent['template'] = self::$template_stack[1];
		if (isset(self::$site_stack[1])) $parent['site'] = self::$site_stack[1];
		
		if (!empty($parent)) $args['parent'] = $parent;
		
		if ($file = self::exists($site, $template, $controller_name, $view_name)) {
			if (isset($controller)) extract(get_object_vars($controller));
			
			ob_start();
			include($file);
			$output = ob_get_clean();
		} else {
			throw new \ErrorException('Can\'t find view. View: '.$view_name.', Controller: '.$controller_name.', Site: '.$site.', Template: '.$template);
		}
		
		if ($site_name) array_shift(self::$site_stack);
		if ($template_name) array_shift(self::$template_stack);
		array_shift(self::$controller_stack);
		array_shift(self::$view_stack);
		
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
	
	public static function flash($msg=false, $bucket=0)
	{
		if ($msg) {
			if (!isset(Session::$d['flash'][$bucket])) {
				Session::$d['flash'][$bucket] = array();
			}
			Session::$d['flash'][$bucket][] = $msg;
			return;
		}
		
		if (!empty(Session::$d['flash'][$bucket])) {
			$out = '';
			foreach (Session::$d['flash'][$bucket] as $msg) {
				if (substr($msg, 0, 7) == '<script') {
					$out .= $msg;
				} else {
					$out .= '<div class="msg">'.$msg.'</div>';
				}
			}
			unset(Session::$d['flash'][$bucket]);
			return $out;
		}
	}
}