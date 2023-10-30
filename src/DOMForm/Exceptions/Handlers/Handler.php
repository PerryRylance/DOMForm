<?php

namespace PerryRylance\DOMForm\Exceptions\Handlers;

use PerryRylance\DOMDocument\DOMElement;

use PerryRylance\DOMForm\Exceptions\Population\PopulationException;

abstract class Handler
{
	abstract public function handle(PopulationException $exception): void;
}