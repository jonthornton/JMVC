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

	public function to()
	{
		$this->to = array_unique(array_merge($this->to, func_get_args()));
		return $this;
	}

	public function subject($subject)
	{
		$this->subject = html_entity_decode($subject);
		return $this;
	}

	public function tag()
	{
		//stubbed out for now
	}

	public function fromName($name)
	{
		$this->fromName = $name;
	}

	public function replyTo($email)
	{
		$this->replyTo = $email;
	}

	public function messagePlain($message)
	{
		$this->plain_body = $message;

		if (!$this->body) {
			$this->body = $message;
		}

		return $this;
	}

	public function messageHtml($message)
	{
		$this->body = $message;

		return $this;
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
		$mail->FromName = $this->fromName ?: MAIL_FROM_NAME;
		$mail->Sender = MAIL_REPLY_TO;

		if ($this->replyTo) {
			$mail->AddReplyTo($this->replyTo);
		}

		$mail->Body = $this->body;

		if (substr(trim($this->body), 0, 1) == '<') {
			$mail->IsHTML(true);

			if (isset($this->plain_body)) {
				$mail->AltBody = html_entity_decode($this->plain_body);
			}
		} else {
			$mail->Body = html_entity_decode($this->body);
			$mail->IsHTML(false);
		}

		if (IS_PRODUCTION) {
			return $mail->Send();
		}
	}
}
