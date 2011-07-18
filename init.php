<?php

use jmvc\Controller;
use jmvc\View;
use jmvc\classes\Benchmark;

class JMVC {

	public static function init($doRouting=true)
	{
		include(JMVC_DIR.'classes/benchmark.php');
		Benchmark::start('total');
		
		include(APP_DIR.'../config.php');
		
		include(JMVC_DIR.'view.php');
		include(JMVC_DIR.'controller.php');

		if (file_exists(APP_DIR.'routes.php')) {
			include(APP_DIR.'routes.php');
		}
		
		if (file_exists(APP_DIR.'constants.php')) {
			include(APP_DIR.'constants.php');
		}
		
		date_default_timezone_set('America/New_York');
		spl_autoload_register('JMVC::autoloader');
		set_exception_handler('JMVC::exception_handler');
		set_error_handler('JMVC::exception_error_handler');
		
		if ($qPos = strpos($_SERVER['REQUEST_URI'], '?')) {
			define('CURRENT_URL', substr(strtolower($_SERVER['REQUEST_URI']), 0, $qPos));
			define('QUERY_STRING', substr($_SERVER['REQUEST_URI'], $qPos));
		} else {
			define('CURRENT_URL', strtolower($_SERVER['REQUEST_URI']));
			define('QUERY_STRING', '');
		}
		
		if (isset($REDIRECTS)) {
			foreach ($REDIRECTS as $in=>$out) {
				$routed_url = preg_replace('%'.$in.'%', $out, CURRENT_URL, 1, $count);

				if ($count) {
					if (strlen(QUERY_STRING)) {
						$q = '?'.QUERY_STRING;
					}
					\jmvc\Controller::forward($routed_url.$q, true);
					break;
				}
			}
		}

		if (!$doRouting) {
			return;
		}
		
		ob_start('JMVC::fatal_error_checker');

		$app_url = CURRENT_URL;
		
		if (!defined('DEFAULT_SITE')) define('DEFAULT_SITE', 'www');
		if (!defined('DEFAULT_TEMPLATE')) define('DEFAULT_TEMPLATE', 'html');
		if (!defined('DEFAULT_CONTROLLER')) define('DEFAULT_CONTROLLER', 'home');
		if (!defined('DEFAULT_VIEW')) define('DEFAULT_VIEW', 'index');
		
		if ($app_url == '/') {
			$site = DEFAULT_SITE;
			$template = DEFAULT_TEMPLATE;
			$args['controller'] = DEFAULT_CONTROLLER;
			$args['view'] = DEFAULT_VIEW;
			$parts = array();
		} else {
		
			$parts = explode('/', trim($app_url, '/'));

			// block direct url for default site
			if ($parts[0] == DEFAULT_SITE) self::do404();
			$site = Controller::exists($parts[0], 'template') ? array_shift($parts) : DEFAULT_SITE;
		
			if ($parts[0] == DEFAULT_TEMPLATE) self::do404();
			$template = method_exists('controllers\\'.$site.'\Template', $parts[0]) ? array_shift($parts) : DEFAULT_TEMPLATE;
			
			if (isset($ROUTES)) {
				$app_url = '/'.implode('/', $parts).'/';
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
						
						$parts = explode('/', trim($app_url, '/'));
						break;
					}
				}
			}
		
			$possible_controller = str_replace('-', '_', $parts[0]);
			if ($possible_controller == DEFAULT_CONTROLLER) self::do404();
			
			if (Controller::exists($site, $possible_controller)) {
				$args['controller'] = $possible_controller;
				array_shift($parts);
			} else {
				$args['controller'] = DEFAULT_CONTROLLER;
			}
			
			$possible_view = str_replace('-', '_', $parts[0]);
			if ($possible_view == DEFAULT_VIEW) self::do404();
			if (count($parts) && View::exists($site, $template, $args['controller'], $possible_view, true)) {
				$args['view'] = $possible_view;
				array_shift($parts);
			} else {
				$args['view'] = DEFAULT_VIEW;
			}
		}
		
		if ($hook_output = self::hook('post_routing', $args+array('site'=>$site, 'template'=>$template))) {
			$args = $hook_output;
			$site = $args['site'];
			$template = $args['template'];
		}

		$args['controller'] = str_replace('-', '_', $args['controller']);
		$args['view'] = str_replace('-', '_', $args['view']);
		
		if ($args['controller'] == 'template') {
			self::do404(false);
		}
		
		echo render('template', $template, array_merge($parts, $args), null, $site, $template);
	}

	public static function do404($template=true)
	{
		ob_end_clean();
	
		header("HTTP/1.0 404 Not Found");
		if ($template) {
			echo render('template', DEFAULT_TEMPLATE, array('controller'=>'template', 'view'=>'do404'));
		} else {
			echo 'Page not found.';
		}
		exit;
	}
	
	public static function hook($hook, $args)
	{
		if (is_callable($GLOBALS['HOOKS'][$hook])) {
			return $GLOBALS['HOOKS'][$hook]($args);
		}
	}
	
	public static function autoloader($classname)
	{
		$parts = explode('\\', $classname);

		switch ($parts[0]) {
			case 'jmvc':
			
				switch ($parts[1]) {
					case 'classes':
						$filename = JMVC_DIR.'classes/'.strtolower($parts[2]).'.php';
						break;
						
					case 'models':
						$filename = JMVC_DIR.'models/'.strtolower($parts[2]).'.php';
						break;
						
					default:
						$filename = JMVC_DIR.strtolower($parts[1]).'.php';
						break;
				}
				break;
			
			case 'models':
				$filename = APP_DIR.'models/'.strtolower($parts[1]).'.php';
				break;
				
			case 'controllers':
				$filename = APP_DIR.'sites/'.$parts[1].'/'.strtolower($parts[2]).'.php';
				break;
				
			default:
				$filename = APP_DIR.strtolower($classname).'.php';
		}
		
		if (file_exists($filename)) {
			include($filename);
		} else {
			throw new \ErrorException('Couldn\'t load '.$classname.'; was looking at '.$filename);
		}
	}
	
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
	
	public function log($data, $logname=false)
	{
		$host = $_SERVER['HTTP_HOST'] ?: 'cmd';
		if ($logname) $host .= '.';
		$log = @fopen(LOG_DIR.'/'.$host.$logname.'.log', 'a');
		
		if ($log) {
			fwrite($log, $data."\n\n");
			fclose($log);
		}
	}
	
	public static function exception_handler($ex)
	{
		// clear the output buffer
		$a = 0;
		while(ob_end_clean()) { $a++; }
		
		if (IS_PRODUCTION) {
			self::notify_admin($ex->getFile(), self::make_error_report($ex->getFile(), $ex->getLine(), $ex->getMessage()));
			header('HTTP/1.1 500 Internal Server Error');
			exit();
			
		} else {
			include(JMVC_DIR.'exception_html.php');
		}

	}
	
	public static function exception_error_handler($errno, $errstr, $errfile, $errline)
	{
		if ($errno > 2) {
			return;
		}
		
		throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
	
	public static function fatal_error_checker($output)
	{
		if ($error = error_get_last()) {
			if ($error['type'] <= 2) {
				if (IS_PRODUCTION) {
					self::notify_admin($error['file'], self::make_error_report($error['file'], $error['line'], $error['message']));
					header('HTTP/1.1 500 Internal Server Error');
					exit();
				} else {
					header('Content-type: text/plain');
					return self::make_error_report($error['file'], $error['line'], $error['message']);
				}
			}
		}
		
		return $output;
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
		return 'An error occurred on line '.$line.' of '.$file.'

'.$message.'

REQUEST URI: http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'

SERVER: '.print_array($_SERVER).'

GET: '.print_array($_GET).'

POST: '.print_array($_POST).' 

SESSION: '.print_array(jmvc\classes\Session::$d);
	}
}

function render($controller, $view, $args=array(), $cache=null, $site=false, $template=false)
{
	return jmvc\View::render($controller, $view, $args, $cache, $site, $template);
}

function print_array($arr, $padding="\t")
{
	$outp = "{\n";
	
	foreach ($arr as $key=>$value) {
		$outp .= $padding.$key.' => ';
		
		if (is_array($value)) {
			$outp .= print_array($value, $padding."\t")."\n";
		} else {
			$outp .= $value."\n";
		}
	}
	
	return $outp.substr($padding,0, -1).'}';
}

function pp($data)
{
	if (!IS_PRODUCTION) {
		echo '<pre>'.htmlspecialchars(print_r($data,true)).'</pre>';
	}
}

function pd($data)
{
	if (!IS_PRODUCTION) {
		pp($data);
		die();
	}
}