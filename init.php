<?php

use jmvc\Controller;
use jmvc\View;
use jmvc\classes\Benchmark;

class JMVC {

	/**
	 * Main bootstrapping function. Initialize framework, perform routing, hand things off to template controller.
	 * @return void
	 */
	public static function init()
	{
		// Load classes that will always be used; faster than autoloader
		include(JMVC_DIR.'classes/benchmark.php');
		Benchmark::start('total');

		include(CONFIG_FILE);

		include(JMVC_DIR.'view.php');
		include(JMVC_DIR.'controller.php');

		if (file_exists(APP_DIR.'routes.php')) {
			include(APP_DIR.'routes.php');
		}

		if (file_exists(APP_DIR.'constants.php')) {
			include(APP_DIR.'constants.php');
		}

		date_default_timezone_set('America/New_York'); // TODO: make this configurable
		spl_autoload_register(array('JMVC', 'autoloader'));

		// set error handling
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		set_exception_handler(array('JMVC', 'handle_exception'));
		set_error_handler(array('JMVC', 'handle_error'), E_ERROR | E_WARNING);
		register_shutdown_function(array('JMVC', 'fatal_error_checker'));

		if (!isset($_SERVER['REQUEST_URI'])) { //don't do routing if we're not running as a web server process
			return;
		}

		// define some helper constants
		if ($qPos = strpos($_SERVER['REQUEST_URI'], '?')) {
			define('CURRENT_URL', substr(strtolower($_SERVER['REQUEST_URI']), 0, $qPos));
			define('QUERY_STRING', substr($_SERVER['REQUEST_URI'], $qPos));
		} else {
			define('CURRENT_URL', strtolower($_SERVER['REQUEST_URI']));
			define('QUERY_STRING', '');
		}

		define('IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']));

		// check if we need to do a URL redirect (301)
		if (isset($REDIRECTS)) {
			foreach ($REDIRECTS as $in=>$out) {
				$routed_url = preg_replace('%'.$in.'%', $out, CURRENT_URL, 1, $count);

				if ($count) {
					\jmvc\Controller::forward($routed_url.QUERY_STRING, true);
					break;
				}
			}
		}

		$app_url = CURRENT_URL;

		// Check for any internal URL mapping
		if (isset($ROUTES)) {
			$app_url = '/'.implode('/', $url_parts).'/';
			foreach ($ROUTES as $in=>$out) {
				$routed_url = preg_replace('%'.$in.'%', $out, $app_url, 1, $count);

				if ($count) {
					$routed_parts = explode('?', $routed_url);
					$app_url = $routed_parts[0];

					if (isset($routed_parts[1]) && !empty($routed_parts[1])) {
						foreach (explode('&', $routed_parts[1]) as $pair) {
							list($key, $val) = explode('=', $pair);
							$_GET[$key] = $val;
							$_REQUEST[$key] = $val;
						}
					}

					break;
				}
			}
		}

		// Set default context
		if (defined('DEFAULT_SITE')) \jmvc\View::$CONTEXT_DEFAULTS['site'] = DEFAULT_SITE;
		if (defined('DEFAULT_TEMPLATE')) \jmvc\View::$CONTEXT_DEFAULTS['template'] = DEFAULT_TEMPLATE;
		if (defined('DEFAULT_CONTROLLER')) \jmvc\View::$CONTEXT_DEFAULTS['controller'] = DEFAULT_CONTROLLER;
		if (defined('DEFAULT_VIEW')) \jmvc\View::$CONTEXT_DEFAULTS['view'] = DEFAULT_VIEW;

		// routing
		if ($app_url == '/') {
			$context = \jmvc\View::$CONTEXT_DEFAULTS;
			$url_parts = array();
		} else {

			// Parse each segment of the URL, left to right
			$url_parts = explode('/', trim($app_url, '/'));

			// Set site
			if (Controller::exists($url_parts[0], 'template')) $context['site'] = array_shift($url_parts);
			if ($context['site'] == \jmvc\View::$CONTEXT_DEFAULTS['site']) { // block direct url for default site
				\jmvc::do404();
			} else if (!isset($context['site'])) {
				$context['site'] = \jmvc\View::$CONTEXT_DEFAULTS['site'];
			}

			// Do not allow access to base class methods
			if (method_exists('jmvc\\controller', $url_parts[0])) {
				\jmvc::do404();
			}

			// Set template
			if (method_exists('controllers\\'.$context['site'].'\Template', $url_parts[0])) $context['template'] = array_shift($url_parts);
			if ($context['template'] == \jmvc\View::$CONTEXT_DEFAULTS['template']) { // block direct url for default template
				\jmvc::do404();
			} else if (!isset($context['template'])) {
				$context['template'] = \jmvc\View::$CONTEXT_DEFAULTS['template'];
			}

			// Get controller
			$possible_controller = str_replace('-', '_', $url_parts[0]);
			if ($possible_controller == \jmvc\View::$CONTEXT_DEFAULTS['controller'] || $possible_controller == 'template') { // block direct url for default controller
				\jmvc::do404();
			}

			if (Controller::exists($context['site'], $possible_controller)) {
				$context['controller'] = $possible_controller;
				array_shift($url_parts);
			} if (!isset($context['controller'])) {
				$context['controller'] = \jmvc\View::$CONTEXT_DEFAULTS['controller'];
			}

			// Get view
			$possible_view = str_replace('-', '_', $url_parts[0]);
			if ($possible_view == \jmvc\View::$CONTEXT_DEFAULTS['view']) { // block direct url for default view
				\jmvc::do404();
			}

			if (count($url_parts) && View::exists($context+array('view'=>$possible_view), true)) {
				$context['view'] = $possible_view;
				array_shift($url_parts);
			} if (!isset($context['view'])) {
				$context['view'] = \jmvc\View::$CONTEXT_DEFAULTS['view'];
			}
		}

		self::hook('post_routing', $context);

		// Hand things off to the template controller
		ob_start();
		echo \jmvc\View::render(array_merge($context, array('controller'=>'template', 'view'=>$context['template'])),
			array_merge($url_parts, array('context'=>$context)));
	}

	/**
	 * Display a 404 error to the user. Stops execution of the script.
	 * @param bool $template
	 * @return void
	 */
	public static function do404()
	{
		// clear the output buffer
		while(ob_get_length()) { ob_end_clean(); }

		header("HTTP/1.0 404 Not Found");

		echo \jmvc\View::render(array('controller'=>'template', 'view'=>\jmvc\View::$CONTEXT_DEFAULTS['template'],
			'site'=>\jmvc\View::$CONTEXT_DEFAULTS['site'], 'template'=>\jmvc\View::$CONTEXT_DEFAULTS['template']),
			array('context'=>array('controller'=>'template', 'view'=>'do404')));
		exit;
	}

	/**
	 * Call a hook during a certain part of the app lifecycle. Hooks are defined in a global $HOOKS array
	 * with keys matching hook names. Only 'post_routing' is supported at this time.
	 * @param strin $hook Hook to call. Only 'post_routing' is supported at this time
	 * @param mixed &$args Arbitrary argument to be passed to hook function. Passed by reference.
	 * @return void
	 */
	public static function hook($hook, &$args)
	{
		if (is_callable($GLOBALS['HOOKS'][$hook])) {
			return $GLOBALS['HOOKS'][$hook]($args);
		}
	}

	/**
	 * Class autoloaded. Translates namespaceing into file paths
	 * @param string $classname
	 * @return void
	 */
	public static function autoloader($classname)
	{
		$classname = strtolower($classname);
		$parts = explode('\\', $classname);

		switch ($parts[0]) {
			case 'jmvc':

				switch ($parts[1]) {
					case 'classes':
						$filename = JMVC_DIR.'classes/'.$parts[2].'.php';
						break;

					case 'models':
						$filename = JMVC_DIR.'models/'.$parts[2].'.php';
						break;

					default:
						$filename = JMVC_DIR.$parts[1].'.php';
						break;
				}
				break;

			case 'models':
				$filename = APP_DIR.'models/'.$parts[1].'.php';
				break;

			case 'controllers':
				$filename = APP_DIR.'sites/'.$parts[1].'/'.$parts[2].'.php';
				break;

			default:
				$filename = APP_DIR.$classname.'.php';
		}

		if (file_exists($filename)) {
			include($filename);
		} else {
			throw new \ErrorException('Couldn\'t load '.$classname.'; was looking at '.$filename);
		}
	}

	/**
	 * Return cleaned data from superglobals
	 * @param string $name Key
	 * @param string $global Superglobal to access
	 * @param bool $raw Only trim the value, don't clean. Defaults to false
	 * @return string
	 */
	public static function input($name, $global=null, $raw=false)
	{
		switch (strtolower($global)) {
			case 'get':
				if (isset($_GET[$name])) $inp = $_GET[$name];
				break;

			case 'post':
				if (isset($_POST[$name])) $inp = $_POST[$name];
				break;

			case 'cookie':
				if (isset($_COOKIE[$name])) $inp = $_COOKIE[$name];
				break;

			case 'server':
				if (isset($_SERVER[$name])) $inp = $_SERVER[$name];
				break;

			default:
				if (isset($_REQUEST[$name])) $inp = $_REQUEST[$name];
				break;
		}

		if (isset($inp)) {
			if ($raw) {
				return trim($inp);
			} else {
				return htmlspecialchars(strip_tags(trim($inp)), ENT_COMPAT, 'ISO-8859-1', false);
			}
		}
	}

	/**
	 * Append data to a file.
	 * @param string $data Data to be recorded
	 * @param string $logname Optional
	 * @return void
	 */
	public static function log($data, $logname=false)
	{
		$host = $_SERVER['HTTP_HOST'] ?: 'cmd';
		if ($logname) $host .= '.';
		$log = @fopen(LOG_DIR.'/'.$host.$logname.'.log', 'a');

		if ($log) {
			fwrite($log, $data."\n\n");
			fclose($log);
		}
	}

	/**
	 * Retrieve an connection to the cache
	 * @return \jmvc\classes\Cache_Interface
	 */
	public static function cache()
	{
		static $driver_instance = false;

		if (!$driver_instance) {
			if (isset($GLOBALS['_CONFIG']['cache_driver'])) {
				$driver = $GLOBALS['_CONFIG']['cache_driver'];
				$driver_instance = $driver::instance();
			} else {
				throw new \Exception('cache_driver not set!');
			}
		}

		return $driver_instance;
	}

	public static function handle_exception($ex)
	{
		// clear the output buffer
		while(ob_get_length()) { ob_end_clean(); }

		if (IS_PRODUCTION) {
			self::notify_admin($ex->getFile(), self::make_error_report($ex->getFile(), $ex->getLine(), $ex->getMessage()));
			header('HTTP/1.1 500 Internal Server Error');

		} else {
			include(JMVC_DIR.'exception_html.php');
		}

		die();
	}

	public static function handle_error($errno, $errstr, $errfile, $errline)
	{
		if (error_reporting() == 0) return;
		throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}

	public static function fatal_error_checker()
	{
		if ($error = error_get_last()) {
			if ($error['type'] != 8 && (!IS_PRODUCTION || $error['type'] <= 2)) {
				self::handle_exception(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
			}
		}
	}

	private static function notify_admin($file, $message)
	{
		self::log(date('r')."\n".$message, 'php_errors');

		if (!file_exists(LOG_DIR.'/error_state')) {
			mail(ADMIN_EMAIL, 'Error in '.$file, $message);

			if (defined('ADMIN_ALERT')) {
				mail(ADMIN_ALERT, 'Error on '.$_SERVER['HTTP_HOST'], 'check email');
			}

			touch(LOG_DIR.'/error_state');
		}
	}

	private static function make_error_report($file, $line, $message)
	{
		$out = 'An error occurred on line '.$line.' of '.$file.'

'.$message.'

REQUEST URI: http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n\n";

if (!empty($_GET)) {
	$out .= 'GET: '.print_r($_GET, true)."\n\n";
}

if (!empty($_POST)) {
	$out .= 'POST: '.print_r($_POST, true)."\n\n";
}

if (!empty(jmvc\classes\Session::$d)) {
	$out .= 'SESSION: '.print_r(jmvc\classes\Session::$d, true)."\n\n";
}

if (!empty($_SERVER)) {
	$out .= 'SERVER: '.print_r($_SERVER, true)."\n\n";
}

		return $out;
	}
}

/**
 * Dump the contents of a variable with some pretty formatting
 * @param mixed $data
 * @return void
 */
function pp($data)
{
	if (!IS_PRODUCTION) {
		echo '<pre>'.htmlspecialchars(print_r($data,true)).'</pre>';
	}
}

/**
 * Dump the contents of a variable and exit(). Similar to pp()
 * @param mixed $data
 * @return void
 */
function pd($data)
{
	if (!IS_PRODUCTION) {
		pp($data);
		die();
	}
}
