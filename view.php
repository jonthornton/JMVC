<?php

namespace jmvc;

use jmvc\classes\Session;

class View {

	protected static $data;
	protected static $cacheme = array();
	protected static $stacks = array('site'=>array(), 'template'=>array(), 'controller'=>array(), 'view'=>array());
	
	protected static $CONTEXT_PARTS = array('site', 'template', 'controller', 'view');
	public static $CONTEXT_DEFAULTS = array('site'=>'www', 'template'=>'html', 'controller'=>'home', 'view'=>'index');
	
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
	
	public static function cache_key($view, $args)
	{
		return md5(serialize(array_merge($view, $args)));
	}
	
	public static function bust_cache($view, $args)
	{
		$key = self::cache_key($view, $args);
		self::cache()->delete($key);
		self::cache()->delete($key.'meta');
	}

	public static function render($view_name=null, $args=array(), $cache_expires=null)
	{
		$context = self::push_context($view_name, $parent);
		
		if (method_exists('jmvc\\Controller', $context['view'])) {
			\jmvc::do404();
		}
		
		if (!empty($parent)) $args['parent'] = $parent;

		$controller = false;
		
		if ($cache_expires !== null) {
			$key = 'view'.self::cache_key($context, $args);
			
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
		
		$controller = Controller::factory($context, $args);
		if ($controller && method_exists($controller, $context['view'])) {
			$controller->$context['view']();
			
			if ($override = $controller->view_override()) {
				$context = self::push_context($override);
			}
			
		} else {
			$controller = false;
		}
		
		if ($view_file = self::exists($context)) {
			if ($controller) extract(get_object_vars($controller));
			
			ob_start();
			include($view_file);
			$output = ob_get_clean();
		}

		// must find either a controller method or view file
		if (!$controller && !$view_file) {
			throw new \ErrorException('Can\'t find view. View: '.$context['view'].', Controller: '.$context['controller'].', 
				Template: '.$context['template'].', Site: '.$context['site']);
		}
		
		if ($override) {
			self::pop_context($override);
		}
		self::pop_context($view_name);
		
		if ($cache_expires !== null) {
			self::cache()->set($key, $output, $cache_expires);
			self::cache()->set($key.'meta', self::$cacheme[$key], $cache_expires);
			
			unset(self::$cacheme[$key]);
		}
		
		return $output;
	}

	public static function render_static($view_name=null, $args=array())
	{
		$context = self::push_context($view_name, $parent);
		
		if (method_exists('jmvc\\Controller', $context['view'])) {
			\jmvc::do404();
		}
		
		if (!empty($parent)) $args['parent'] = $parent;
		
		if ($view_file = self::exists($context)) {
			ob_start();
			include($view_file);
			$output = ob_get_clean();
		} else {
			throw new \ErrorException('Can\'t find view. View: '.$context['view'].', Controller: '.$context['controller'].', 
				Template: '.$context['template'].', Site: '.$context['site']);
		}
		
		self::pop_context($view_name);
		
		return $output;
	}
	
	private function push_context($context_args, &$parent=array())
	{
		// build context and update context stack
		if (is_array($context_args)) {
			// view, controller, template, and site arguments as an array
			$context = $context_args;
			
			foreach (self::$CONTEXT_PARTS as $part) {
				if (isset($context[$part])) array_unshift(self::$stacks[$part], $context[$part]);
			}
			
		} else if (is_string($context_args)) {
			// get controller, template, and site from stack/defaults
			$context['view'] = $context_args;
			array_unshift(self::$stacks['view'], $context_args);
		}
		
		$same_view = true;
		foreach (self::$CONTEXT_PARTS as $part) {
			if (!isset($context[$part])) {
				$context[$part] = self::$stacks[$part][0] ?: self::$CONTEXT_DEFAULTS[$part];
			}
			
			if ($context[$part] != self::$stacks[$part][1]) $same_view = false;
			if (isset(self::$stacks[$part][1])) $parent[$part] = self::$stacks[$part][1];
		}
		
		if ($same_view) {
			throw new \ErrorException('Attempted double rendering. View: '.$context['view'].', Controller: '.$context['controller'].', 
				Template: '.$context['template'].', Site: '.$context['site']);
		}
	
		return $context;
	}
	
	private function pop_context($context_args)
	{
		// remove additions to the context stacks
		if (is_array($context_args)) {
			foreach (self::$CONTEXT_PARTS as $part) {
				if (isset($context_args[$part])) array_shift(self::$stacks[$part]);
			}
		} else if (is_string($context_args)) {
			array_shift(self::$stacks['view']);
		}
	
	}
	
	public static function exists($context, $check_controller=false)
	{
		$file = APP_DIR.'sites/'.$context['site'].'/'.$context['template'].'/'.$context['controller'].'.'.$context['view'].'.php';
		if (file_exists($file)) {
			return $file;
		}
		
		$file = APP_DIR.'sites/'.$context['site'].'/'.self::$CONTEXT_DEFAULTS['template'].'/'.$context['controller'].'.'.$context['view'].'.php';
		if (file_exists($file)) {
			return $file;
		}
		
		if ($check_controller && Controller::exists($context['site'], $context['controller']) 
			&& method_exists('controllers\\'.$context['site'].'\\'.$context['controller'], $context['view'])) {
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