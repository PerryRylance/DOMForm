<?php

namespace PerryRylance\DOMForm;

use DateTime;
use Exception;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMObject;
use PerryRylance\DOMForm\Exceptions\BadValueException;
use PerryRylance\DOMForm\Exceptions\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\DatetimeFormatException;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\InvalidRegexException;
use PerryRylance\DOMForm\Exceptions\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\ValueRequiredException;
use PerryRylance\DOMForm\Exceptions\ReadonlyException;
use PerryRylance\DOMForm\Handlers\DOMFormPopulateErrorHandler;

class DOMFormElement extends DOMElement
{
	const DATETIME_LOCAL_FORMAT = 'Y-m-d\TH:i';
	const MONTH_FORMAT = 'Y-m';
	const WEEK_FORMAT = 'Y-\WW';
	const TIME_FORMAT = 'H:i';

	public static DOMFormPopulateErrorHandler $defaultPopulateErrorHandler;

	protected function assertIsForm(): void
	{
		if(!preg_match('/^form$/i', $this->nodeName))
			throw new ElementNotFormException("Failed to assert element is a form");
	}

	protected function parseDatetime(string $raw, string $type, DOMFormPopulateErrorHandler $errorHandler, DOMFormElement $element, &$numErrors)
	{
		switch(strtolower($type))
		{
			case "datetime-local":
				$format = static::DATETIME_LOCAL_FORMAT;
				break;
			
			case "month":
				$format = static::MONTH_FORMAT;
				break;
			
			case "time":
				$format = static::TIME_FORMAT;
				break;

			case "week":
				break;

			default:
				throw new Exception("Unknown datetime element type");
		}

		if($type === "week")
		{
			// NB: Looks like PHP supports writing weeks, but doesn't support parsing weeks. So we have to shim this in.
			if(!preg_match('/^(\d{4})-W(\d{1,2})$/', $raw, $m))
			{
				$errorHandler->handle(new DatetimeFormatException("Value does not match expected format"), $element);
				$numErrors++;
				$result = false; // NB: Imitate DateTime::createFromFormat
			}
			else
			{
				$year = (int)$m[1];
				$week = (int)$m[2];

				$result = new DateTime();
				$result->setTimestamp(strtotime("First Monday of $year"));
				$result->modify("+$week week");
			}
		}
		else
			$result = DateTime::createFromFormat($format, $raw);

		if($result === false)
		{
			$errorHandler->handle(new DatetimeFormatException("Value does not match expected format"), $element);
			$numErrors++;
		}

		return $result;
	}

	/**
	 * This method takes data as an input and attempts to populate the form from the input, validating using HTML5's validation attributes. If validation passes, this method returns the validated data. If any validation fails, this method returns false.
	 * 
	 * @param iterable $data The input data, for example $_POST
	 * @param DOMFormPopulateErrorHandler|null $errorHandler The error handler to use. Defaults to DOMFormPopulateErrorHandler, which throws an exception and halts on bad or missing values.
	 * 
	 * @return array|false The validated, serialized data on success, or false if validation failed.
	 */
	public function populate(iterable $data, ?DOMFormPopulateErrorHandler $errorHandler = null): array | false
	{
		$numErrors = 0;
		
		$this->assertIsForm();

		if(!isset(DOMFormElement::$defaultPopulateErrorHandler))
			DOMFormElement::$defaultPopulateErrorHandler = new DOMFormPopulateErrorHandler();
		
		if(is_null($errorHandler))
			$errorHandler = DOMFormElement::$defaultPopulateErrorHandler;

		if(is_array($data) && array_is_list($data))
			throw new Exception('Expected associative array');

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
				$type = strtolower($target->attr("type") ?? "text");
				$name = strtolower($element->nodeName);

				switch($name)
				{
					case "input":

						if($element->hasAttribute('pattern'))
						{
							$pattern	= $element->getAttribute('pattern');
							
							// NB: Suppress the possible warning, since we catch that ourselves just under here
							$result		= @preg_match("/$pattern/", $value);

							if($result === false)
								throw new InvalidRegexException();
							
							if($result === 0)
							{												$errorHandler->handle(new BadValueException("Value does not match specified pattern"), $element);
								$numErrors++;
							}
						}

						switch($type)
						{
							case "url":

								if(!filter_var($value, FILTER_VALIDATE_URL))
								{
									$errorHandler->handle(new BadValueException("Invalid URL"), $element);
									$numErrors++;
								}

								break;

							case "email":

								if($element->hasAttribute('multiple'))
								{
									$emails = explode(',', $value);

									foreach($emails as $email)
										if(!filter_var($email, FILTER_VALIDATE_EMAIL))
										{
											$errorHandler->handle(new BadValueException("One or more e-mail addresses are invalid"), $element);
											$numErrors++;
										}
								}
								else
									if(!filter_var($value, FILTER_VALIDATE_EMAIL))
									{
										$errorHandler->handle(new BadValueException("Invalid email address"), $element);
										$numErrors++;
									}

								break;
							
							case "number":
							case "range":

								if(!is_numeric($value))
								{
									$errorHandler->handle(new BadValueException("Invalid number"), $element);
									$numErrors++;
								}
								
								if($element->hasAttribute("min") && $value < $element->getAttribute("min"))
								{
									$errorHandler->handle(new BadValueException("Below minimum"), $element);
									$numErrors++;
								}
								
								if($element->hasAttribute("max") && $value > $element->getAttribute("max"))
								{
									$errorHandler->handle(new BadValueException("Above maximum"), $element);
									$numErrors++;
								}
								
								if($element->hasAttribute("step"))
								{
									$step = $element->getAttribute("step");
									$isFloatingPoint = strpos($step, '.') !== false;

									if($isFloatingPoint)
									{
										$remainder = fmod(abs($value), $step);

										// NB: Delta threshold at 1e-15
										if($remainder >= 1e-15)
										{
											$errorHandler->handle(new BadValueException("Out of sequence"), $element);
											$numErrors++;
										}
									}
									else
									{
										$remainder = abs($value) % $step;

										if($remainder !== 0)
										{
											$errorHandler->handle(new BadValueException("Out of sequence"), $element);
											$numErrors++;
										}
									}
								}

								break;
							
							case "color":

								if(!preg_match('/^#[0-f]{6}$/i', $value))
								{
									$errorHandler->handle(new BadValueException("Not a valid color"), $element);
									$numErrors++;
								}

								break;
							
							case "datetime-local":
							case "month":
							case "week":
							case "time":

								$datetime = $this->parseDatetime($value, $type, $errorHandler, $element, $numErrors);

								if($element->hasAttribute('min'))
								{
									$min = $this->parseDatetime($element->getAttribute('min'), $type, $errorHandler, $element, $numErrors);

									if($datetime < $min)
									{
										$errorHandler->handle(new BadValueException("Below minimum"), $element);
										$numErrors++;
									}
								}

								if($element->hasAttribute('max'))
								{
									$max = $this->parseDatetime($element->getAttribute('max'), $type, $errorHandler, $element, $numErrors);

									if($datetime > $max)
									{
										$errorHandler->handle(new BadValueException("Above maximum"), $element);
										$numErrors++;	
									}
								}

							default:

								$element->setAttribute('value', $value);

								break;
						}

					case "textarea":
					case "select":

						if($element->hasAttribute("required") && empty($value))
						{
							$errorHandler->handle(new ValueRequiredException("Must be filled"), $element);
							$numErrors++;
						}
						
						if($element->hasAttribute("readonly"))
						{
							$errorHandler->handle(new ReadonlyException("Field is read only"), $element);
							$numErrors++;
						}
						
						if($element->hasAttribute("disabled"))
						{
							$errorHandler->handle(new ReadonlyException("Field is disabled"), $element);
							$numErrors++;
						}
						
						if($name === 'select')
						{
							$isMultiSelect = $element->hasAttribute('multiple');

							if($isMultiSelect)
							{
								if(!preg_match('/\[\]$/', $element->getAttribute('name')))
									trigger_error('Expected multi-select to have array brackets at end of name', E_USER_WARNING);

								if(!is_array($value))
								{
									$errorHandler->handle(new BadValueException('Expected an array for multi-select'), $element);
									$numErrors++;
								}
								
								if(!array_is_list($value))
								{
									$errorHandler->handle(new BadValueException("Expected an indexed array for multi-select"), $element);
									$numErrors++;
								}
								
								// NB: Explicitly unselect any initially selected values, see https://github.com/PerryRylance/DOMDocument/issues/63
								$target
									->find("option[selected]")
									->removeAttr("selected");

								$values = $value;
							}
							else
								$values = [$value];

							foreach($values as $unescaped)
							{
								$escaped = addslashes($unescaped);
								$option = $target->find("option[value='$escaped']");

								if(!count($option))
								{
									$errorHandler->handle(new BadValueException("Specified selection is invalid"), $element);
									$numErrors++;
								}

								if($isMultiSelect)
									$option->attr("selected", "selected");
							}
							
							if($isMultiSelect)
								break; // NB: We explicitly set these already, so we can bail here. See https://github.com/PerryRylance/DOMDocument/issues/63
						}

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
				{
					$errorHandler->handle(new CheckboxRequiredException("Must be checked"), $checkbox);
					$numErrors++;
				}

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
				{
					// TODO: Enhance, only flag this up on the last element.
					$errorHandler->handle(new RadioRequiredException('Selection required'), $radio);
					$numErrors++;
				}

				continue;
			}

			if($data[$name] != $radio->getAttribute('value'))
				continue;
			
			$escaped = addslashes($name);

			$self->find("input[type='radio'][name='$escaped'][checked]")->removeAttr("checked");

			$radio->setAttribute("checked", "checked");
		}

		if($numErrors > 0)
			return false;
		
		return $this->serialize();
	}

	/**
	 * This method serializes the data in the form. Useful for getting defaults from the HTML. If you are trying to retrieve serialized and validated data, it's better to use the return value from populate - this function can potentially give you bad data on it's own eg after a failed call to populate.
	 * 
	 * @return array The serialized data from the forms inputs
	 */
	public function serialize(): array
	{
		$this->assertIsForm();

		$result = [];

		foreach($this->querySelectorAll("input[name], select[name], textarea[name]") as $element)
		{
			$source = new DOMObject($element);
			$name	= $source->attr("name");

			switch(strtolower($element->nodeName))
			{
				case "select":

					$selected		= $source->find("option[selected]");

					if($element->hasAttribute('multiple'))
					{
						if(!preg_match('/\[\]$/', $name))
							trigger_error('Expected multi-select to have array brackets at end of name', E_USER_WARNING);
						else
						{
							$result[$name] = array_map(function($element) {
								return $element->getAttribute('value');
							}, $selected->toArray());

							break;
						}
					}

					$all			= $source->find("option");

					if(count($selected))
						$options	= $selected;
					else
						$options	= $all;
					
					if(!count($options))
						break; // NB: Don't crash when there are no options

					$result[$name] = $options[0]->hasAttribute('value') ? $options[0]->getAttribute('value') : $options[0]->nodeValue;

					break;

				default:

					switch($source->attr("type"))
					{
						case "checkbox":
						case "radio":

							if(!$source->is("[checked]"))
								break 2; // NB: Omit unchecked checkboxes and ignore unchecked radios

							break;
					}

					$result[$name] = $source->val();

					break;
			}
		}

		return $result;
	}
}