<?php

namespace jmvc\models;
use jmvc\Model;

class Postmark_Mail_Queue extends Model {

	protected static $_table = 'postmark_mail_queue';
	
	public static function get_batch()
	{
		return self::find(array(), 5);
	}
	
	public static function clear_ids($ids)
	{
		if (!$ids || !is_array($ids) || empty($ids)) {
			return;
		}
		
		$idStr = '';
		foreach ($ids as $id) {
			$idStr .= self::quote($id).',';
		}
		
		self::db()->delete('DELETE FROM postmark_mail_queue WHERE id IN('.substr($idStr, 0, -1).')');
	}
}