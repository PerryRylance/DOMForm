<?php

namespace PerryRylance\DOMForm\Exceptions\Handlers;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMForm\Exceptions\Population\PopulationException;
use PerryRylance\DOMForm\Exceptions\Handlers\Handler;

class DisplayHtml extends Handler
{
	public function handle(PopulationException $exception): void
	{
		$element = $exception->element;

		$span = $element->ownerDocument->createElement('span');
		$text = $element->ownerDocument->createTextNode($exception->getMessage());

		$span->setAttribute('class', 'error');
		$span->appendChild($text);

		$element->parentNode->appendChild($span);
	}
}