<?php

use jmvc\Controller;
use jmvc\View;
use jmvc\classes\Benchmark;

class JMVC {

	public static function init()
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
		ob_start('JMVC::fatal_error_checker');

		if (!isset($_SERVER['REQUEST_URI'])) { //don't do routing if we're not running as a web server process
			return;
		}
		
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
		
		if (defined('DEFAULT_SITE')) \jmvc\View::$CONTEXT_DEFAULTS['site'] = DEFAULT_SITE;
		if (defined('DEFAULT_TEMPLATE')) \jmvc\View::$CONTEXT_DEFAULTS['template'] = DEFAULT_TEMPLATE;
		if (defined('DEFAULT_CONTROLLER')) \jmvc\View::$CONTEXT_DEFAULTS['controller'] = DEFAULT_CONTROLLER;
		if (defined('DEFAULT_VIEW')) \jmvc\View::$CONTEXT_DEFAULTS['view'] = DEFAULT_VIEW;
		
		$app_url = CURRENT_URL;
		
		if ($app_url == '/') {
			$context = \jmvc\View::$CONTEXT_DEFAULTS;
			$url_parts = array(); 
		} else {
		
			$url_parts = explode('/', trim($app_url, '/'));
			
			
			if (Controller::exists($url_parts[0], 'template')) $context['site'] = array_shift($url_parts);
			if ($context['site'] == \jmvc\View::$CONTEXT_DEFAULTS['site']) { // block direct url for default site
				\jmvc::do404();
			} else if (!isset($context['site'])) {
				$context['site'] = \jmvc\View::$CONTEXT_DEFAULTS['site'];
			}
			
			if (method_exists('jmvc\\controller', $url_parts[0])) {
				\jmvc::do404();
			}
			
			if (method_exists('controllers\\'.$context['site'].'\Template', $url_parts[0])) $context['template'] = array_shift($url_parts);
			if ($context['template'] == \jmvc\View::$CONTEXT_DEFAULTS['template']) { // block direct url for default template
				\jmvc::do404();
			} else if (!isset($context['template'])) {
				$context['template'] = \jmvc\View::$CONTEXT_DEFAULTS['template'];
			}
			
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
						
						$url_parts = explode('/', trim($app_url, '/'));
						break;
					}
				}
			}
		
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
	
		echo \jmvc\View::render(array_merge($context, array('controller'=>'template', 'view'=>$context['template'])), 
			array_merge($url_parts, array('context'=>$context)));
	}

	public static function do404($template=true)
	{
		ob_end_clean();
	
		header("HTTP/1.0 404 Not Found");
		
		echo \jmvc\View::render(array('controller'=>'template', 'view'=>\jmvc\View::$CONTEXT_DEFAULTS['template'], 
			'site'=>\jmvc\View::$CONTEXT_DEFAULTS['site'], 'template'=>\jmvc\View::$CONTEXT_DEFAULTS['template']), 
			array('context'=>array('controller'=>'template', 'view'=>'do404')));
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

SERVER: '.return_print_r($_SERVER).'

GET: '.return_print_r($_GET).'

POST: '.return_print_r($_POST).' 

SESSION: '.return_print_r(jmvc\classes\Session::$d);
	}
}


function return_print_r($var, $html = false, $level = 0) {
	$spaces = "";
	$space = $html ? "&nbsp;" : " ";
	$newline = $html ? "<br />" : "\n";
	
	for ($i = 1; $i <= 6; $i++) {
		$spaces .= $space;
	}
	
	$tabs = $spaces;
	for ($i = 1; $i <= $level; $i++) {
		$tabs .= $spaces;
	}
	
	if (is_array($var)) {
		$title = "Array";
	} elseif (is_object($var)) {
		$title = get_class($var)." Object";
	}
	
	$output = $title . $newline . $newline;
	
	foreach($var as $key => $value) {
		if (is_array($value) || is_object($value)) {
			$level++;
			$value = return_print_r($value, true, $html, $level);
			$level--;
		}
		$output .= $tabs . "[" . $key . "] => " . $value . $newline;
	}
	
	return $output;
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