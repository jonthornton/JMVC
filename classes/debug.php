<?php

namespace jmvc\classes;

class Debug {

	public static $links = array();

	private function __construct()
	{
		// this is a static-only class
	}

	private function table_names($query)
	{
		if (preg_match_all('/(FROM|JOIN|INTO|UPDATE)\s+([a-z0-9_]+)/i', $query, $matches)) {
			return implode(', ', array_unique($matches[2]));
		}
	}

	public static function add_link($label, $link_text, $url)
	{
		self::$links[] = array('label'=>$label, 'link_text'=>$link_text, 'url'=>$url);
	}

	public static function make_toolbar()
	{
		$content = '';
		$infowindows = '';
		ob_start();

		if (!empty(self::$links)) {
			echo '<ul class="panel links">';
			foreach (self::$links as $link) {
				echo'<li>'.$link['label'].': <a href="'.$link['url'].'">'.$link['link_text'].'</a></li>';
			}
			echo '</ul>';
		}


		$stats = \jmvc\Db::$stats;
		if (is_array($stats)) {
			$total = $stats['select'] + $stats['insert'] + $stats['update'] + $stats['delete'];

			echo '<div class="panel">
				<h3>Database Stats</h3>
				<table class="data">
					<tr>
						<td><strong>Total</strong></td>
						<td class="num"><strong>'.$total.'</strong></td>
					</tr>
					<tr>
						<td>Select</td>
						<td class="num">'.$stats['select'].'</td>
					</tr>
					<tr>
						<td>Insert</td>
						<td class="num">'.$stats['insert'].'</td>
					</tr>
					<tr>
						<td>Update</td>
						<td class="num">'.$stats['update'].'</td>
					</tr>
					<tr>
						<td>Delete</td>
						<td class="num">'.$stats['delete'].'</td>
					</tr>
				</table>';

			$queries = \jmvc\Db::$queries;
			if (is_array($queries) && !empty($queries)) {
				$rows = '';
				foreach ($queries as $query) {
					$rows .= '<tr>
						<td class="num">'.round($query['time']*1000).'ms</td>
						<td>'.self::table_names($query['query']).'
						</td>
						<td><a href="#" class="showquery">Show Query</a>
							<div class="query">'.nl2br($query['query']).'</div></td>
					</tr>';
				}

				$infoWindows .= '<div id="jmvc-debug-dbqueries">
					<table class="data">
						'.$rows.'
					</table>
				</div>';

				echo '<a href="#" rel="jmvc-debug-dbqueries" class="jmvc-debug-infoWindowLink">Show DB Queries</a>';
			}

			echo '</div>';
		}

		$stats = \jmvc\classes\Cache_Interface::$stats;
		if (is_array($stats)) {
			echo '<div class="panel">
				<h3>Cache Stats</h3>
				<table class="data">
					<tr>
						<td>Hits</td>
						<td class="num">'.$stats['hits'].'</td>
					</tr>
					<tr>
						<td>Misses</td>
						<td class="num">'.$stats['misses'].'</td>
					</tr>
					<tr>
						<td>Writes</td>
						<td class="num">'.$stats['writes'].'</td>
					</tr>
				</table>';

			if (!empty($stats['keys'])) {
				$rows = '';
				foreach ($stats['keys'] as $key) {
					$rows .= '<tr>
						<td>'.$key[0].'</td>
						<td>'.$key[1].'</td>
					</tr>';
				}

				$infoWindows .= '<div id="jmvc-debug-cache-keys">
					<table class="data">
						'.$rows.'
					</table>
				</div>';

				echo '<a href="#" rel="jmvc-debug-cache-keys" class="jmvc-debug-infoWindowLink">Show Keys</a>';
			}

			echo '</div>';
		}

		if (isset($GLOBALS['_CONFIG']['redis'])) {
			$r = new \Redis();
			$r->connect($GLOBALS['_CONFIG']['redis']['host'], $GLOBALS['_CONFIG']['redis']['port']);

			$mail_count = $r->llen('jmvc:rmail');

			if (IS_PRODUCTION && $mail_count) {
				$encoded_message = $r->lindex('jmvc:rmail', 0);
				$message = json_decode($encoded_message);
				if (time() - $message->created > 1800) {
					throw new \Exception('Mail queue failure!');
				}
			}

			$jobs_count = $r->llen('JMVC:jobs:low') + $r->llen('JMVC:jobs:high');

			if (IS_PRODUCTION && $jobs_count > 200) {
				throw new \Exception('Job queue failure!');
			}
		}

		$content = ob_get_clean();
		$b = \jmvc\classes\Benchmark::get('total');
		$display = (isset($_COOKIE['jmvc-debug-toolbar'])) ? '' : 'style="display:none;"';

		return '<div id="jmvc-debug-container">
			<div id="jmvc-debug-toolbar" '.$display.'>
				'.$content.'

				<ul class="panel">
					<li class="jmvc-debug-toggle-option" id="jmvc-bust-cache">Cache Buster</li>
					<li>'.round($b['time']*1000).'ms</li>
					<li>'.($mail_count ?: 0).' unsent emails</li>
					<li>'.($jobs_count ?: 0).' pending jobs</li>
				</ul>
				<div style="clear: both"></div>
			</div>
			<div class="jmvc-debug-toggle">X</div>
		</div>
		<div id="jmvc-debug-infoWindows">'.$infoWindows.'</div>

		<script type="text/javascript" src="/js/debug.js"></script>
		';
	}

}
