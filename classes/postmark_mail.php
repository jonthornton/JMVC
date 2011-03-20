<?
namespace jmvc\classes;

class Postmark_Mail extends \jmvc\classes\Mail {
	
	public function send()
	{
		$m = new \jmvc\classes\Postmark();
		
		foreach ($this->to as $addr) {
			$m->to($addr);
		}
		
		$m->subject($this->subject);
		
		if (strpos($this->body, '<br') !== false || strpos($this->body, '<p>') !== false) {
		
			if (\jmvc\View::get('use_template') && \jmvc\View::exists('www', 'html', 'template', 'email')) {
				$this->body = \jmvc\View::render_static('template', 'email', array('content'=>$this->body), 'www', 'html');
			}
			
			$m->messageHtml($this->body);
			
			if (isset($this->plain_body)) {
				$m->messagePlain($this->plain_body);
			}
		} else {
			$m->messagePlain($this->body);
		}
		
		if (IS_PRODUCTION) {
			return $m->send();
		}
	}
}
