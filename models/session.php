<?php

namespace jmvc\models;
use jmvc\Model;

class Session extends Model {

	protected static $table = 'sessions';
	
	public static function clean_old()
	{
		self::db()->delete('DELETE FROM sessions WHERE modified < "'.date('Y-m-d H:i:s', time()-ONE_DAY).'"');
	}
}