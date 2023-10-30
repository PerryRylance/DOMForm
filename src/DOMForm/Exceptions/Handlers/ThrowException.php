<?php

namespace PerryRylance\DOMForm\Exceptions\Handlers;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMForm\Exceptions\Handlers\Handler;
use PerryRylance\DOMForm\Exceptions\Population\PopulationException;

class ThrowException extends Handler
{
	public function handle(PopulationException $exception): void
	{
		// NB: The default behaviour implemented on this base class is to simply throw the exception
		throw $exception;
	}
}