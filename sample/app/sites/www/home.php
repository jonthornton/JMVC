<?php

namespace controllers\www;

class Home extends \jmvc\Controller {

	public function index()
	{
		if ($this->args[0]) {
			\Util::do404();
		}
	}
}