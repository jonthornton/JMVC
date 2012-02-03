<?php
namespace jmvc\classes;

class RMail {

	protected $envelope = array(
		'subject'=>null,
		'to'=>array(),
		'from'=>MAIL_REPLY_TO,
		'from_name'=>MAIL_FROM_NAME,
		'reply_to'=>MAIL_REPLY_TO,
		'plain_body'=>null,
		'html_body'=>null,
		'tags'=>array(),
		'send'=>true
	);

	protected static $r;

	public function __construct()
	{
		$this->envelope['to'] = func_get_args();
		$this->envelope['created'] = time();

		if (!IS_PRODUCTION) {
			$this->envelope['send'] = false;
		}
	}

	public function to()
	{
		$this->envelope['to'] = array_unique(array_merge($this->envelope['to'], func_get_args()));
	}

	public function subject($subject)
	{
		$this->envelope['subject'] = html_entity_decode($subject);
	}

	public function tag($in)
	{
		$tags = is_array($in) ? $in : func_get_args();
		$this->envelope['tags'] = array_unique(array_merge($this->envelope['tags'], $tags));
	}

	public function fromName($name)
	{
		$this->envelope['from_name'] = $name;
	}

	public function replyTo($email)
	{
		$this->envelope['reply_to'] = $email;
	}

	public function messagePlain($message)
	{
		$this->envelope['plain_body'] = $message;
	}

	public function messageHtml($message)
	{
		$this->envelope['html_body'] = $message;
	}

	public function send()
	{
		if (!self::$r) {
			// initialize redis connection
			$config = $GLOBALS['_CONFIG']['redis'];
			self::$r = new \Redis();
			if (!self::$r->connect($config['host'], $config['port'])) {
				throw new \Exception('Error connecting to redis server at '.$config['host'].' port '.$config['port']);
			}
		}

		self::$r->rpush('jmvc:rmail', json_encode($this->envelope));
	}
}
