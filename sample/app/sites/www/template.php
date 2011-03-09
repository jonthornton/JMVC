<?

namespace controllers\www;

class Template extends \jmvc\Controller {

	public function html()
	{
		$this->content = render($this->args['controller'], $this->args['view'], $this->args);
	}
}