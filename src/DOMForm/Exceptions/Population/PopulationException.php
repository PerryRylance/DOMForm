<?php

namespace PerryRylance\DOMForm\Exceptions\Population;

use Exception;
use Throwable;

use PerryRylance\DOMDocument\DOMElement;

class PopulationException extends Exception
{
	public function __construct(
		public readonly DOMElement $element, 
		string $message, 
		int $code = 0, 
		?Throwable $previous = null
	)
	{
		Parent::__construct($message, $code, $previous);
	}
}