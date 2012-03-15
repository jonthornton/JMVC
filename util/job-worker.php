<?php
// Instructions: set this to be run by cron once per minute, and use Solo to prevent more than 1 instance from running
// http://timkay.com/solo/

include('cron_helper.php');

$redis = \jmvc::Redis();

$job_count = 0;
while ($job = $redis->blpop('JMVC:jobs:high', 'JMVC:jobs:low', 0)) {
	$job_count++;
	$job = json_decode($job[1]);

	if ($job->obj_id) {
		// instantiate object
		$classname = $job->class;
		$obj = $classname::factory($job->obj_id);

		if (!$obj) {
			throw \Exception($job->class.' #'.$job->obj_id.' not found!');
		}

		$callback = array($obj, $job->method);
	} else {
		// call static method
		$callback = array($job->class, $job->method);
	}

	if (!is_callable($callback)) {
		throw \Exception('Method not found!');
	}

	call_user_func_array($callback, $job->args);

	if ($job_count > 100) {
		// die and let cron restart the script, just in case PHP is leaking memory
		die;
	}
}
