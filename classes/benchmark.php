<?php
/**
 * Based on the Kohana benchmark class
 */

namespace jmvc\classes;

final class Benchmark {

	private static $marks;

	public static function start($name)
	{
		if (!isset(self::$marks[$name])) {
			self::$marks[$name] = array();
		}

		array_unshift(self::$marks[$name], array('start'=>microtime(TRUE), 'stop'=>FALSE, 'memory_start'=>memory_get_usage(), 'memory_stop'=>FALSE));
	}

	public static function stop($name)
	{
		if (isset(self::$marks[$name]) AND self::$marks[$name][0]['stop'] === FALSE) {
			self::$marks[$name][0]['stop'] = microtime(TRUE);
			self::$marks[$name][0]['memory_stop'] = memory_get_usage();
		}
	}

	public static function get($name, $decimals = 4)
	{
		if ($name === TRUE) {
			$times = array();

			foreach (array_keys(self::$marks) as $name) {
				$times[$name] = self::get($name, $decimals);
			}

			return $times;
		}

		if (!isset(self::$marks[$name])) return FALSE;

		if (self::$marks[$name][0]['stop'] === FALSE) {
			// Stop the benchmark to prevent mis-matched results
			self::stop($name);
		}

		// Return a string version of the time between the start and stop points
		// Properly reading a float requires using number_format or sprintf
		$time = $memory = 0;
		for ($i = 0; $i < count(self::$marks[$name]); $i++) {
			$time += self::$marks[$name][$i]['stop'] - self::$marks[$name][$i]['start'];
			$memory += self::$marks[$name][$i]['memory_stop'] - self::$marks[$name][$i]['memory_start'];
		}

		return array(
			'time'   => number_format($time, $decimals),
			'memory' => $memory,
			'count'  => count(self::$marks[$name])
		);
	}
}
