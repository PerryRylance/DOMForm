<?php

namespace PerryRylance\DOMForm;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMObject;
use PerryRylance\DOMForm\Exceptions\BadValueException;
use PerryRylance\DOMForm\Exceptions\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\ValueRequiredException;
use PerryRylance\DOMForm\Exceptions\ReadonlyException;

class DOMFormElement extends DOMElement
{
	protected function assertIsForm(): void
	{
		if(!preg_match('/^form$/i', $this->nodeName))
			throw new ElementNotFormException("Failed to assert element is a form");
	}

	public function getInputs(?string $name = null): DOMObject
	{
		$self = new DOMObject($this);

		if(is_null($name))
			return $self->find("[name], [data-name]");

		$escaped = addslashes($name);

		return $self->find("[name='$escaped'], [data-name='$escaped']");
	}

	public function populate(iterable $data): void
	{
		$this->assertIsForm();

		$self = new DOMObject($this);

		foreach($data as $key => $value)
		{
			$escaped = addslashes($key);

			$elements = $this
				->querySelectorAll("[name='$escaped'], [data-name='$escaped']")
				->toArray();

			if(empty($elements))
				throw new NoElementsToPopulateException("Failed to find element named '$key'");
			
			$elements = array_map(function($el) { return new DOMObject($el); }, $elements);
			
			foreach($elements as $target)
			{
				$element = $target[0];

				switch(strtolower($element->nodeName))
				{
					case "input":

						switch(strtolower($target->attr("type") ?? "text"))
						{
							case "url":

								if(!filter_var($value, FILTER_VALIDATE_URL))
									throw new BadValueException("Invalid URL");

								break;

							case "email":

								if($element->hasAttribute('multiple'))
								{
									$emails = explode(',', $value);

									foreach($emails as $email)
										if(!filter_var($email, FILTER_VALIDATE_EMAIL))
											throw new BadValueException("One or more e-mail addresses are invalid");
								}
								else
									if(!filter_var($value, FILTER_VALIDATE_EMAIL))
										throw new BadValueException("Invalid email address");

								break;
							
							case "number":
							case "range":

								if(!is_numeric($value))
									throw new BadValueException("Invalid number");
								
								if($element->hasAttribute("min") && $value < $element->getAttribute("min"))
									throw new BadValueException("Below minimum");
								
								if($element->hasAttribute("max") && $value > $element->getAttribute("max"))
									throw new BadValueException("Above maximum");
								
								if($element->hasAttribute("step"))
								{
									$step = $element->getAttribute("step");
									$isFloatingPoint = strpos($step, '.') !== false;

									if($isFloatingPoint)
									{
										$remainder = fmod(abs($value), $step);

										// NB: Delta threshold at 1e-15
										if($remainder >= 1e-15)
											throw new BadValueException("Out of sequence");
									}
									else
									{
										$remainder = abs($value) % $step;

										if($remainder !== 0)
											throw new BadValueException("Out of sequence");
									}
								}

								break;
							
							case "color":

								if(!preg_match('/^#[0-f]{6}$/i', $value))
									throw new BadValueException("Not a valid color");

								break;

							default:

								$element->setAttribute('value', $value);

								break;
						}

					case "textarea":
					case "select":

						if($element->hasAttribute("required") && empty($value))
							throw new ValueRequiredException("Must be filled");
						
						if($element->hasAttribute("readonly"))
							throw new ReadonlyException("Field is read only");
						
						if($element->hasAttribute("disabled"))
							throw new ReadonlyException("Field is disabled");

						$target->val($value);

						break;
					
					default:
						$target->text($value);
						break;
				}
			}
		}

		// NB: Handle checkboxes "checked" separately, it works on set / unset
		$dataAsArray = (array)$data;

		foreach($this->querySelectorAll("input[type='checkbox'][name]") as $checkbox)
		{
			$name		= $checkbox->getAttribute('name');
			$checked	= isset($dataAsArray[$name]);
			$required	= $checkbox->hasAttribute('required');

			if($checked)
				$checkbox->setAttribute('checked', 'checked');
			else
			{
				if($required)
					throw new CheckboxRequiredException();

				$checkbox->removeAttribute('checked');
			}
		}

		// NB: Now handle radios
		$isRadioRequiredByName = [];

		foreach($this->querySelectorAll("input[type='radio'][name][required]") as $radio)
			$isRadioRequiredByName[$radio->getAttribute('name')] = true;

		foreach($this->querySelectorAll("input[type='radio'][name]") as $radio) 
		{
			$name = $radio->getAttribute('name');

			if(!isset($data[$name]))
			{
				if(isset($isRadioRequiredByName[$name]))
					throw new RadioRequiredException();

				continue;
			}

			if($data[$name] != $radio->getAttribute('value'))
				continue;
			
			$escaped = addslashes($name);

			$self->find("input[type='radio'][name='$escaped'][checked]")->removeAttr("checked");

			$radio->setAttribute("checked", "checked");
		}
	}

	public function serialize(): array
	{
		$this->assertIsForm();

		$result = [];

		foreach($this->querySelectorAll("input[name], select[name], textarea[name]") as $element)
		{
			$source = new DOMObject($element);
			$name	= $source->attr("name");

			switch($source->attr("type"))
			{
				case "checkbox":
				case "radio":

					if(!$source->is("[checked]"))
						continue 2; // NB: Omit unchecked checkboxes and ignore unchecked radios

					break;
			}

			$result[$name] = $source->val();
		}

		return $result;
	}
}