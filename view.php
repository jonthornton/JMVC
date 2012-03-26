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

	/**
	 * Retrieve a global value set by another view. Useful for passing data between views.
	 * @param type $key
	 * @return type
	 */
	public static function get($key)
	{
		if (isset(self::$data[$key])) {
			return self::$data[$key];
		}
	}

	/**
	 * Push data onto a global stack. Good for passing data to parent views
	 * @param string $key
	 * @param mixed $val
	 * @param bool $unique If true, don't allow duplicates. Defaults to false.
	 * @return void
	 */
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


	/**
	 * Set a global value that can be accessed by other view. Good for passing data to parent views.
	 * @param string $key
	 * @param mixed $val
	 * @return mixed
	 */
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

	/**
	 * Over-write a previously set value
	 * @param string $key
	 * @param string $val
	 * @return void
	 */
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
		return md5(serialize(array_merge($view, (array)$args)));
	}

	public static function bust_cache($view, $args)
	{
		$key = self::cache_key($view, $args);
		self::cache()->delete($key);
		self::cache()->delete($key.'meta');
	}

	/**
	 * Render a view. Will use context to call controller method, than load corresponding view. Only one
	 * or the other is required.
	 * @param mixed $view_name May be string or context array
	 * @param array $args Arbitrary data to pass to controller, view
	 * @param int $cache_expires Cache life in seconds
	 * @return void
	 */
	public static function render($view_name=null, $args=array(), $cache_expires=null)
	{
		$context = self::push_context($view_name, $parent);
		\jmvc::trace('render '.$context['controller'].'.'.$context['view']);

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

		ob_start();
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
			include($view_file);
		}

		$output = ob_get_clean();

		// must find either a controller method or view file
		if (!$controller && !$view_file) {
			throw new \ErrorException('Can\'t find view. View: '.$context['view'].', Controller: '.$context['controller'].',
				Template: '.$context['template'].', Site: '.$context['site']);
		}

		if (isset($override) && $override) {
			self::pop_context($override);
		}
		self::pop_context($view_name);

		if ($cache_expires !== null) {
			if ($cache_expires == 'controller') {
				$cache_expires = $controller->cache_expires;
			}

			self::cache()->set($key, $output, $cache_expires);
			self::cache()->set($key.'meta', self::$cacheme[$key], $cache_expires);

			unset(self::$cacheme[$key]);
		}

		return $output;
	}

	/**
	 * Render a view without attempting to call a controller. Similar to render().
	 * @param mixed $view_name
	 * @param array $args
	 * @return void
	 */
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

	/**
	 * Track the context as views are loaded
	 * @param array $context_args
	 * @param array &$parent
	 * @return array
	 */
	private static function push_context($context_args, &$parent=array())
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


			if (isset(self::$stacks[$part][1])) {
				if ($context[$part] != self::$stacks[$part][1]) $same_view = false;
				$parent[$part] = self::$stacks[$part][1];
			} else {
				$same_view = false;
			}
		}

		if ($same_view) {
			throw new \ErrorException('Attempted double rendering. View: '.$context['view'].', Controller: '.$context['controller'].',
				Template: '.$context['template'].', Site: '.$context['site']);
		}

		return $context;
	}

	private static function pop_context($context_args)
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

	/**
	 * Check if a view file exists
	 * @param array $context
	 * @param bool $check_controller Check for the existence of a corresponding controller method
	 * @return bool
	 */
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

	/**
	 * Set and retrieve session data. Acts as a simple message queue to pass messages across requests.
	 * If called with a $msg argument, set data. Otherwise, retrieve all messages in the specified bucket.
	 * Returns formatted HTML.
	 * @param string $msg
	 * @param string $bucket
	 * @return string
	 */
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
