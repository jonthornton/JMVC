<?php

if (!defined('JMVC_CRON_HELPER')) {
	define('JMVC_CRON_HELPER', true);

	define('JMVC_DIR', __DIR__.'/../../jmvc/');
	define('APP_DIR', __DIR__.'/../');
	define('CONFIG_FILE', __DIR__.'/../../config.php');

	require_once(JMVC_DIR.'init.php');
	JMVC::init(false);
}
