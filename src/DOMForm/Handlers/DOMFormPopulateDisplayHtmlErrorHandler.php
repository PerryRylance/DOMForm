<?php

namespace PerryRylance\DOMForm\Handlers;

use PerryRylance\DOMForm\Exceptions\PopulationException;
use PerryRylance\DOMForm\DOMFormElement;

class DOMFormPopulateDisplayHtmlErrorHandler extends DOMFormPopulateErrorHandler
{
	public function handle(PopulationException $exception, DOMFormElement $element): void
	{
		$span = $element->ownerDocument->createElement('span');
		$text = $element->ownerDocument->createTextNode($exception->getMessage());

		$span->setAttribute('class', 'error');
		$span->appendChild($text);

		$element->parentNode->appendChild($span);
	}
}