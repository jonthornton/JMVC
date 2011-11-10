<?
namespace jmvc\classes;

class Mail {

	protected $to = array();
	public $subject;
	public $body;
	public $plain_body;

	public function __construct()
	{
		$this->to = array_unique(array_merge($this->to, func_get_args()));
	}

	public function add_to()
	{
		$this->to = array_unique(array_merge($this->to, func_get_args()));
	}

	public function send()
	{
		$mail = new PHPMailer();

		foreach ($this->to as $to) {
			$mail->AddAddress($to);
		}

		$mail->IsMail();

		$mail->Subject = $this->subject;
		$mail->From = MAIL_REPLY_TO;
		$mail->FromName = MAIL_FROM_NAME;
		$mail->Body = $this->body;

		if (substr(trim($this->body), 0, 1) == '<') {
			$mail->IsHTML(true);

			if (isset($this->plain_body)) {
				$mail->AltBody = $this->plain_body;
			}
		} else {
			$mail->IsHTML(false);
		}

		if (IS_PRODUCTION) {
			return $mail->Send();
		}
	}
}
