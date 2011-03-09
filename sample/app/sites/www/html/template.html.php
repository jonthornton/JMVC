<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?=(self::get('metaDescription') ? '<meta name="DESCRIPTION" content="'.self::get('metaDescription').'" />' : '')?>
	
	<title><?=self::get('title')?></title>
	
	<meta property="og:title" content="<?=(self::get('fb_title') ?: self::get('title'))?>" />
	<? if ($desc = self::get('fb_description')) echo '<meta property="og:description" content="'.$desc.'" />'; ?>
	<meta property="og:image" content="<?=(self::get('fb_image') ?: '/images/p_logo.png')?>" />
	
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="stylesheet" type="text/css" href="/css/base.css" />
	
	<? if ($_SERVER['HTTPS']) { ?>
		<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js"></script>
		<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>
	<? } else { ?>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js"></script>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>
	<? } ?>
	
	<? if ($scripts = self::get('scripts')) { 
		foreach ($scripts as $script) { ?>
			<script type="text/javascript" src="<?=$script?>"></script>
		<? }
	} ?>

	<!--[if IE 6]>
		<link rel="stylesheet" type="text/css" href="/css/ie6.css" media="all"/>
	<![endif]-->
	<!--[if IE 7]>
		<link rel="stylesheet" type="text/css" href="/css/ie7.css" media="all"/>
	<![endif]-->
	
	<script type="text/javascript">
	// <![CDATA[
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', 'GA_ACCOUNT']);
		_gaq.push(['_setDomainName', 'none']);
	// ]]>
	</script>
	
	<? if ($styles = self::get('styles')) { ?>
		<style type="text/css">
		<?=implode("\n", $styles)?>
		</style>
	<? } ?>
</head>

<body class="<?=self::get('selected')?>">

<? if (!IS_PRODUCTION) { ?>
	<div class="printHide" id="test-marker">TEST SITE</div>
<? } ?>

<div id="wrapper">
	<div id="header">
	</div>
	
	<div id="content">

		<?=$content?>

	</div>
	
	<div style="clear:both"></div>
	
	<div id="footer">
	</div>
</div>
	
	<? if (IS_PRODUCTION) { ?>
	
		<script type="text/javascript">
			_gaq.push(['_trackPageview']);
			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(ga);
			})();
		</script>
	
	<? } ?>
</body>

</html>
