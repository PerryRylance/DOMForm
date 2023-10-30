<?php

namespace PerryRylance\DOMForm\Exceptions\Population;

// NB: Thrown when there are no target elements for the input. This is a problem with the underlying data (eg stored or user input), the form cannot be populated.

class NoElementsToPopulateException extends PopulationException
{
	public function __construct() {}
}