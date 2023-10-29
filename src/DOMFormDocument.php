<?php

namespace PerryRylance;

use PerryRylance\DOMDocument;
use PerryRylance\DOMForm\DOMFormElement;

class DOMFormDocument extends DOMDocument
{
	public function __construct($version='1.0', $encoding='UTF-8')
	{
		Parent::__construct($version, $encoding);

		$this->registerNodeClass('DOMElement', DOMFormElement::class);
	}

	public function __get($name)
	{
		if($name == "forms")
			return $this->find("form");
	}
}