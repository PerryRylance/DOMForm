<?php

namespace PerryRylance\DOMForm;

class PopulateOptions
{
	public array $ignoreExceptions = [];

	public function __construct(?array $options = null)
	{
		if($options)
			foreach($this as $key => $unused)
				if(isset($options[$key]))
					$this->{$key} = $options[$key];
	}
}