<?php

namespace PerryRylance\DOMForm\Handlers;

use PerryRylance\DOMForm\Exceptions\PopulationException;
use PerryRylance\DOMForm\DOMFormElement;

class DOMFormPopulateErrorHandler
{
	public function handle(PopulationException $exception, DOMFormElement $element): void
	{
		// NB: The default behaviour implemented on this base class is to simply throw the exception
		throw $exception;
	}
}