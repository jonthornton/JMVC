<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<title>Error on line <?=$ex->getLine()?> of <?=$ex->getFile()?></title>
	
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.0/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="/css/exception.css" />

</head>
		
<body>
	
	<div id="right">
		<h3 onclick="$('#get_vars').toggle(250)">$_GET</h3>
		<div id="get_vars" style="display:none"><?=var_table($_GET)?></div>
		
		<h3 onclick="$('#post_vars').toggle(250)">$_POST</h3>
		<div id="post_vars" style="display:none"><?=var_table($_POST)?></div>
		
		<h3 onclick="$('#server_vars').toggle(250)">$_SERVER</h3>
		<div id="server_vars" style="display:none"><?=var_table($_SERVER)?></div>
		
		<h3 onclick="$('#session_vars').toggle(250)">SESSION</h3>
		<div id="session_vars" style="display:none"><?=var_table(jmvc\classes\Session::$d)?></div>
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
			
			foreach ($trace as $row) { ?>
				<div class="trace_row">
					<h3><?=$row['class']?>::<?=$row['function']?></h3>
					<div>Line <?=$row['line']?> of <?=$row['file']?></div>
				</div>
			<? } ?>
	</div>
</body>

</html>

<?php
function var_table($var) {
	if (!is_array($var) || count($var) == 0) {
		return;
	}
	
	$outp = '<table class="data">';
	
	foreach ($var as $key=>$val) {
		$outp .= '<tr><td>'.$key.'</td><td>'.$val.'</td></tr>';
	}
	
	return $outp.'</table>';
}
?>

