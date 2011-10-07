<?

namespace controllers\www;

class Template extends \jmvc\Controller {

	public function html()
	{
		$this->content = \jmvc\View::render($this->args['context'], $this->args);
	}
}