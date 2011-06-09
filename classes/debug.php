<?php

namespace jmvc\classes;

class Debug {

	public static $panels = array();
	
	private function __construct()
	{
		// this is a static-only class
	}
	
	private function table_names($query)
	{
		if (preg_match_all('/(FROM|JOIN|INTO|UPDATE)\s+([a-z0-9]+)/i', $query, $matches)) {
			return implode(', ', array_unique($matches[2]));
		}
	}
	
	public static function add_panel($label, $link_text, $url)
	{
		self::$panels[] = array('label'=>$label, 'link_text'=>$link_text, 'url'=>$url);
	}
	
	public static function make_toolbar()
	{
		$content = '';
		$infowindows = '';
		
		foreach (self::$panels as $panel) {
			$content .= '<div class="panel">'.$panel['label'].': <a href="'.$panel['url'].'">'.$panel['link_text'].'</a></div>';
		}
		
		$stats = \jmvc\Db::$stats;
		if (is_array($stats)) {
			$total = $stats['select'] + $stats['insert'] + $stats['update'] + $stats['delete'];
		
			$content .= '<div class="panel">
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
						<td class="num">'.round($query['time'], 4).'</td>
						<td>'.self::table_names($query['query']).' 
						</td>
						<td><a href="#" class="showquery">Show Query</a>
							<div class="query">'.nl2br($query['query']).'</div></td>
					</tr>';
				}
				
				$infoWindows .= '<div id="db_queries">
					<table class="data">
						'.$rows.'
					</table>
				</div>';
				
				$content .= '<a href="#" rel="db_queries" class="infoWindowLink">Show DB Queries</a>';
			}
			
			$content .= '</div>';
		}
		
		$stats = \jmvc\classes\Memcache::$stats;
		if (is_array($stats)) {
			$content .= '<div class="panel">
				<h3>Memcache Stats</h3>
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
				</table>
			</div>';
		}
		
		$stats = \jmvc\classes\File_Cache::$stats;
		if (is_array($stats)) {
			$content .= '<div class="panel">
				<h3>File Cache Stats</h3>
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
				</table>
			</div>';
		}
		
		$b = \jmvc\classes\Benchmark::get('total');
		
		return '<div id="admin_debug">
			<div id="debug_toolbar">
				<h2>Admin Toolbar</h2>
				
				'.$content.'
				
				<ul class="panel options">
					<li id="bust_cache">Cache Buster</li>
				</ul>
			
				<div>'.$b['time'].' sec</div>
			</div>
			<div id="debug_openbutton">X</div>
		</div>
		<div id="infoWindows">'.$infoWindows.'</div>
		
		<script type="text/javascript" src="/js/debug.js"></script>
		';		
	}

}