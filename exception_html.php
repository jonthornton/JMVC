<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<?
if (!function_exists('var_table')) { 
	function var_table($var) {
		if (!is_array($var) || count($var) == 0) {
			return;
		}
		
		$outp = '<table class="data">';
		
		foreach ($var as $key=>$val) {
			$outp .= '<tr><td>'.$key.'</td><td>'.print_r($val, true).'</td></tr>';
		}
		
		return $outp.'</table>';
	}

	function print_args($args)
	{
		$out = array();

		foreach ($args as $arg) {
			if (is_numeric($arg)) {
				$out[] = $arg;
			} else if (is_string($arg)) {
				$out[] = '"'.$arg.'"';
			} else {
				$out[] = print_r($arg, true);	
			}
		}

		return implode(', ', $out);
	}
}
?>


<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<title>Error on line <?=$ex->getLine()?> of <?=$ex->getFile()?></title>
	
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.0/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="/css/exception.css" />

</head>
		
<body>
	
	<div id="right">

		<? if (!empty($_GET)) { ?>
			<h3 onclick="$('#get_vars').slideToggle(200)">$_GET</h3>
			<div id="get_vars" style="display:none"><?=var_table($_GET)?></div>
		<? } ?>

		<? if (!empty($_POST)) { ?>
			<h3 onclick="$('#post_vars').slideToggle(200)">$_POST</h3>
			<div id="post_vars" style="display:none"><?=var_table($_POST)?></div>
		<? } ?>

		<? if (!empty($_SERVER)) { ?>
			<h3 onclick="$('#server_vars').slideToggle(200)">$_SERVER</h3>
			<div id="server_vars" style="display:none"><?=var_table($_SERVER)?></div>
		<? } ?>

		<? if (!empty(jmvc\classes\Session::$d)) { ?>
			<h3 onclick="$('#session_vars').slideToggle(200)">SESSION</h3>
			<div id="session_vars" style="display:none"><?=var_table(jmvc\classes\Session::$d)?></div>
		<? } ?>
		
	</div>

	<div id="left">
		<h1><?=$ex->getMessage()?></h1>
		<? if ($ex->getLine() == 0) { ?>
			<div><? pp($ex->getFile()) ?></div>
		<? } else { ?>
			<div>Error on line <?=$ex->getLine()?> of <?=$ex->getFile()?></div>
		<? } ?>
		
		<h2>Backtrace</h2>
		<? $trace = $ex->getTrace();
			array_shift($trace);
			
			foreach ($trace as $row) { 
				?>
				<div class="trace_row">
					<h3><?=(isset($row['class']) ? $row['class'].'::'.$row['function'] : $row['function'])?>(<span class="args"><?=print_args($row['args'])?></span>)</h3>
					<div>Line <?=$row['line']?> of <?=$row['file']?></div>
				</div>
			<? } ?>
	</div>
</body>

</html>