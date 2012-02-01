<?php

namespace jmvc;

/**
 * Public controller methods map to URLs: /controller_name[/method_name][/$args[0]/$args[1]...]
 * index() is the default method if no method_name is in the URL.
 */
abstract class Controller {

	/**
	 * Constructor
	 * @param array $args Arbitrary data to be passed to the controller
	 * @param array $context The site, template, controller, and view requested
	 * @return \jmvc\Controller
	 */
	public function __construct($args, $context=array())
	{
		$this->args = $args;
		$this->context = $context;
	}

	/**
	 * Object ID based routing. URLs like /controller_name/object_id/method_name/ where object_id is numeric.
	 * 			Call from index() like this:
	 * 			if ($this->route_object(args...)) {
	 * 				return;
	 * 			}
	 * @param string $model Model class to attempt to load
	 * @param string $default Default method to run if URL is /controller_name/object_id/
	 * @param \jmvc\Model &$obj Reference return of found object
	 * @param function $filter_callback Object will be passed to this function; if the function returns false, a 404 will be triggered
	 * @return bool Whether an object was found or not
	 */
	public function route_object($model, $default=false, &$obj=null, $filter_callback=null)
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

		if ($filter_callback && !$filter_callback($obj)) {
			\jmvc::do404();
		}

		$this->view_override(array('view'=>$method));
		$this->$method($obj);
		return true;
	}

	/**
	 * Load a different view instead of the one matching the current context.
	 * @param mixed $context If a string, look for a view by that name within current context.
	 * 						If an array, array keys match the context values to be changed.
	 * @return void
	 */
	public function view_override($context=null)
	{
		if (is_array($context)) {
			$this->context_override = $context;
		} else if (is_string($context)) {
			$this->context_override = array('view'=>$context);
		} else if (isset($this->context_override)) {
			return $this->context_override;
		}
	}

	/**
	 * Redirect the user to a new URL. Terminates execution of current script.
	 * @param strin $url URL to be loaded. Domain name optional.
	 * @param bool $permanent Send a 301 Permanent Redirect. If false, send a 303 Temporarily Moved.
	 * 						Defaults to false.
	 * @return void
	 */
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

	/**
	 * Retreive a controller instance based on context.
	 * @param array $context Site, template, controller, view being requested
	 * @param array $args Arbitrary data to be passed to controller instance
	 * @return \jmvc\Controller
	 */
	public static function factory($context, $args=array())
	{
		if (!self::exists($context['site'], $context['controller'])) {
			return false;
		}

		$controller_name = 'controllers\\'.$context['site'].'\\'.$context['controller'];

		return new $controller_name($args, $context);
	}

	/**
	 * Check if a controller class exists.
	 * @param string $site
	 * @param string $controller
	 * @return bool
	 */
	public static function exists($site, $controller)
	{
		return file_exists(APP_DIR.'sites/'.$site.'/'.strtolower($controller).'.php');
	}

	/**
	 * Set a data value that can be retrieved in future requests. Useful for setting status messages.
	 * @param string $msg
	 * @param string $bucket Optional grouping of flash messages. Used to avoid retrieving all messages at once.
	 * @return void
	 */
	public static function flash($msg, $bucket=0)
	{
		View::flash($msg, $bucket);
	}
}
