<?php

use jmvc\Controller;
use jmvc\View;
use jmvc\classes\Session;

class JMVC {

	public static function init($doRouting=true)
	{
		include(APP_DIR.'../config.php');
		include(APP_DIR.'constants.php');

		if (file_exists(APP_DIR.'routes.php')) {
			include(APP_DIR.'routes.php');
		}

		date_default_timezone_set('America/New_York');
		spl_autoload_register('JMVC::autoloader');
		set_exception_handler('JMVC::exception_handler');
		set_error_handler('JMVC::exception_error_handler');
		ob_start('JMVC::fatal_error_checker');
		
		if ($qPos = strpos($_SERVER['REQUEST_URI'], '?')) {
			define('CURRENT_URL', substr($_SERVER['REQUEST_URI'], 0, $qPos));
			define('QUERY_STRING', substr($_SERVER['REQUEST_URI'], $qPos+1));
		} else {
			define('CURRENT_URL', $_SERVER['REQUEST_URI']);
			define('QUERY_STRING', '');
		}

		Session::start();
		
		if (!$doRouting) {
			return;
		}

		$app_url = CURRENT_URL;

		if (isset($ROUTES)) {
			foreach ($ROUTES as $in=>$out) {
				$routed_url = preg_replace('%'.$in.'%', $out, $app_url, 1, $count);

				if ($count) {
					$app_url = $routed_url;
					break;
				}
			}
		}

		if ($app_url == '/') {
			DEFINE('SITE', 'www');
			$template = 'home';
			$args['controller'] = 'index';
			$args['view'] = 'home';
			$parts = array();
		} else {

			$parts = explode('/', trim($app_url, '/'));

			DEFINE('SITE', (Controller::exists($parts[0], Template)) ? array_shift($parts) : 'www');
			
			$template = (method_exists('controllers\\'.SITE.'\Template', $parts[0])) ? array_shift($parts) : 'home';

			$args['controller'] = (Controller::exists(SITE, $parts[0])) ? array_shift($parts) : 'index';
			$args['view'] = (count($parts) && (View::exists(SITE, $args['controller'], $parts[0]) || method_exists('controllers\\'.SITE.'\\'.$args['controller'], $parts[0]))) ? array_shift($parts) : 'home';

		}

		$args['controller'] = str_replace('-', '_', $args['controller']);
		$args['view'] = str_replace('-', '_', $args['view']);

		echo render('template', $template, array_merge($parts, $args));
	}

	public static function do404()
	{
		echo render('template', 'home', array('controller'=>'template', 'view'=>'do404'));
		exit;
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
				$filename = APP_DIR.$classname.'.php';
		}
		
		if (file_exists($filename)) {
			include($filename);
		} else {
			throw new \ErrorException('Couldn\'t load '.$classname.'; was looking at '.$filename);
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
		if (!file_exists(LOG_DIR.'/error_state')) {
			mail(ADMIN_EMAIL, 'Error in '.$file, $message);
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

function render($controller, $view, $args=array(), $site=SITE)
{
	return jmvc\View::render($controller, $view, $args, $site);
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
	echo '<pre>'.htmlspecialchars(print_r($data,true)).'</pre>';
}

function pd($data)
{
	pp($data);
	die();
}