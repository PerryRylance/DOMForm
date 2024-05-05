<?php

namespace PerryRylance;

use DateTime;
use Exception;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMDocument\DOMObject;

use PerryRylance\DOMForm\Exceptions\Handlers\Handler;
use PerryRylance\DOMForm\Exceptions\Handlers\ThrowException;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\InvalidRegexException;
use PerryRylance\DOMForm\Exceptions\Population\PopulationException;
use PerryRylance\DOMForm\Exceptions\Population\BadValueException;
use PerryRylance\DOMForm\Exceptions\Population\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\DatetimeFormatException;
use PerryRylance\DOMForm\Exceptions\Population\DisabledException;
use PerryRylance\DOMForm\Exceptions\Population\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\Population\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\ValueRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\ReadonlyException;
use PerryRylance\DOMForm\PopulateOptions;
use RangeException;

class DOMForm
{
	const FORMAT_DATETIME_LOCAL = 'Y-m-d\TH:i';
	const FORMAT_MONTH = 'Y-m';
	const FORMAT_WEEK = 'Y-\WW';
	const FORMAT_TIME = 'H:i';

	public readonly DOMElement $element;

	private array $errors;
	private PopulateOptions $populateOptions;

	public function __construct(
		DOMObject | DOMElement $target,
		?array $initialState = null,
		private ?Handler $errorHandler = null
	)
	{
		if($target instanceof DOMObject)
		{
			if(count($target) == 0)
				throw new ElementNotFormException("No element found");
			else if(count($target) > 1)
				throw new Exception("Element is ambiguous");
			
			$this->element = $target[0];
		}
		else
			$this->element = $target;

		if(!preg_match('/^form$/i', $this->element->nodeName))
			throw new ElementNotFormException("Failed to assert element is a form");
		
		if(is_null($this->errorHandler))
			$this->errorHandler = new ThrowException();
		
		if(!is_null($initialState))
		{
			// NB: By default, ignore read only and disabled exceptions. This allows us to prepopulate those fields.
			$this->populate($initialState, new PopulateOptions([
				'ignoreExceptions' => [
					ReadonlyException::class,
					DisabledException::class
				]
			]));
		}
	}

	private function resetPopulationErrors(): void
	{
		$this->errors = [];
	}

	private function handlePopulationError(PopulationException $exception): void
	{
		if(array_search(get_class($exception), $this->populateOptions->ignoreExceptions) !== false)
			return; // NB: Do nothing, ignore the exception

		$this->errorHandler->handle($exception);
		$this->errors []= $exception;
	}

	private function getDatetimeFromString(string $raw, DOMElement $element): DateTime | false
	{
		$type = strtolower( $element->getAttribute("type") ?? "" );

		switch(strtolower($type))
		{
			case "datetime-local":
				$format = static::FORMAT_DATETIME_LOCAL;
				break;
			
			case "month":
				$format = static::FORMAT_MONTH;
				break;
			
			case "time":
				$format = static::FORMAT_TIME;
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
			$this->handlePopulationError(new DatetimeFormatException($element, "Value does not match expected format"));

		return $result;
	}

	private function validateUrl(string $value, DOMElement $element): void
	{
		if(!filter_var($value, FILTER_VALIDATE_URL))
			$this->handlePopulationError(new BadValueException($element, "Invalid URL"));
	}

	private function validateEmail(string $value, DOMElement $element): void
	{
		if($element->hasAttribute('multiple'))
		{
			$emails = explode(',', $value);

			foreach($emails as $email)
				if(!filter_var($email, FILTER_VALIDATE_EMAIL))
					$this->handlePopulationError(new BadValueException($element, "One or more e-mail addresses are invalid"));
		}
		else
			if(!filter_var($value, FILTER_VALIDATE_EMAIL))
				$this->handlePopulationError(new BadValueException($element, "Invalid email address"));
	}

	private function validateColor(string $value, DOMElement $element): void
	{
		if(!preg_match('/^#[0-f]{6}$/i', $value))
			$this->handlePopulationError(new BadValueException($element, "Not a valid color"));
	}

	private function validateDatetime(string $value, DOMElement $element): void
	{
		$datetime = $this->getDatetimeFromString($value, $element);

		if($element->hasAttribute('min'))
		{
			$min = $this->getDatetimeFromString($element->getAttribute('min'), $element);

			if($datetime < $min)
				$this->handlePopulationError(new BadValueException($element, "Below minimum"));
		}

		if($element->hasAttribute('max'))
		{
			$max = $this->getDatetimeFromString($element->getAttribute('max'), $element);

			if($datetime > $max)
				$this->handlePopulationError(new BadValueException($element, "Above maximum"));
		}
	}

	private function validateNumberOrRange(string $value, DOMElement $element): void
	{
		if(!is_numeric($value))
			$this->handlePopulationError(new BadValueException($element, "Invalid number"));
		
		if($element->hasAttribute("min") && $value < $element->getAttribute("min"))
			$this->handlePopulationError(new BadValueException($element, "Below minimum"));
		
		if($element->hasAttribute("max") && $value > $element->getAttribute("max"))
			$this->handlePopulationError(new BadValueException($element, "Above maximum"));
		
		if($element->hasAttribute("step"))
		{
			$step = $element->getAttribute("step");
			$isFloatingPoint = strpos($step, '.') !== false;

			if($isFloatingPoint)
			{
				$remainder = fmod(abs($value), $step);

				// NB: Delta threshold at 1e-15
				if($remainder >= 1e-15)
					$this->handlePopulationError(new BadValueException($element, "Out of sequence"));
			}
			else
			{
				if($value == PHP_INT_MIN)
					throw new RangeException("Number out of processable range (must be greater than PHP_INT_MIN)");

				$remainder = abs($value) % $step;

				if($remainder !== 0)
					$this->handlePopulationError(new BadValueException($element, "Out of sequence"));
			}
		}
	}

	private function validatePattern(string $value, DOMElement $element): void
	{
		$pattern	= $element->getAttribute('pattern');
							
		// NB: Suppress the possible warning, since we catch that ourselves just under here
		$result		= @preg_match("/$pattern/", $value);

		if($result === false)
			throw new InvalidRegexException();
		
		if($result === 0)
			$this->handlePopulationError(new BadValueException($element, "Value does not match specified pattern"));
	}

	private function validateRequired(string $value, DOMElement $element): void
	{
		if(empty($value))
			$this->handlePopulationError(new ValueRequiredException($element, "Must be filled"));
	}

	private function validateReadonly(string $value, DOMElement $element): void
	{
		if($value == $element->getAttribute('value'))
			return; // NB: Allow validation if there is no change. Browsers will send read-only fields, so provided the user hasn't changed anything, this is valid.

		$this->handlePopulationError(new ReadonlyException($element, "Field is read only"));
	}

	private function validateDisabled(string $value, DOMElement $element): void
	{
		$this->handlePopulationError(new DisabledException($element, "Field is disabled"));
	}

	private function populateInput(string $value, DOMElement $element): void
	{
		$type = $element->getAttribute('type') ?? '';

		if($element->hasAttribute('pattern'))
			$this->validatePattern($value, $element);

		switch($type)
		{
			case "url":
				$this->validateUrl($value, $element);
				break;

			case "email":
				$this->validateEmail($value, $element);
				break;
			
			case "number":
			case "range":
				$this->validateNumberOrRange($value, $element);
				break;
			
			case "color":
				$this->validateColor($value, $element);
				break;
			
			case "datetime-local":
			case "month":
			case "week":
			case "time":
				$this->validateDatetime($value, $element);
				break;

			default:
				break;
		}

		$element->setAttribute('value', $value);
	}

	private function populateSelect(string | array $value, DOMElement $element): void
	{
		$target = new DOMObject($element);
		$isMultiSelect = $element->hasAttribute('multiple');

		if($isMultiSelect)
		{
			if(!preg_match('/\[\]$/', $element->getAttribute('name')))
				trigger_error('Expected multi-select to have array brackets at end of name', E_USER_WARNING);

			if(!is_array($value))
				$this->handlePopulationError(new BadValueException($element, 'Expected an array for multi-select'));
			
			if(!array_is_list($value))
				$this->handlePopulationError(new BadValueException($element, "Expected an indexed array for multi-select"));

			$values = $value;
		}
		else
			$values = [$value];

		// NB: Explicitly unselect any initially selected values, see https://github.com/PerryRylance/DOMDocument/issues/63
		$target
			->find("option[selected]")
			->removeAttr("selected");

		foreach($values as $unescaped)
		{
			$escaped = addslashes($unescaped);
			$option = $target->find("option[value='$escaped']");

			if(!count($option))
			{
				$option = null;

				foreach($target->find("option") as $el)
				{
					$el = (new DOMObject($el));

					if($el->text() == $unescaped)
					{
						$option = $el;
						break;
					}
				}

				if(!$option)
					$this->handlePopulationError(new BadValueException($element, "Specified selection is invalid"));
			}

			if($option)
				$option->attr("selected", "selected");
		}
	}

	private function populateCheckboxes(array $data): void
	{
		foreach($this->element->querySelectorAll("input[type='checkbox'][name]") as $checkbox)
		{
			$name		= $checkbox->getAttribute('name');
			$checked	= isset($data[$name]);
			$required	= $checkbox->hasAttribute('required');

			if($checked)
				$checkbox->setAttribute('checked', 'checked');
			else
			{
				if($required)
					$this->handlePopulationError(new CheckboxRequiredException($checkbox, "Must be checked"));

				$checkbox->removeAttribute('checked');
			}
		}
	}

	private function populateRadios(array $data): void
	{
		$isRadioRequiredByName = [];

		foreach($this->element->querySelectorAll("input[type='radio'][name][required]") as $radio)
			$isRadioRequiredByName[$radio->getAttribute('name')] = true;

		foreach($this->element->querySelectorAll("input[type='radio'][name]") as $radio) 
		{
			$name = $radio->getAttribute('name');

			if(!isset($data[$name]))
			{
				if(isset($isRadioRequiredByName[$name]))
					$this->handlePopulationError(new RadioRequiredException($radio, 'Selection required'));

				continue;
			}

			if($data[$name] != $radio->getAttribute('value'))
				continue;
			
			$escaped = addslashes($name);

			(new DOMObject($this->element))
				->find("input[type='radio'][name='$escaped'][checked]")
				->removeAttr("checked");

			$radio->setAttribute("checked", "checked");
		}
	}

	public function submit(array $data)
	{
		return $this->populate($data);
	}

	/**
	 * This method takes data as an input and attempts to populate the form from the input, validating using HTML5's validation attributes. If validation passes, this method returns the validated data. If any validation fails, this method returns false.
	 * 
	 * @param array $data The input data, for example $_POST
	 * @param DOMFormPopulateErrorHandler|null $errorHandler The error handler to use. Defaults to DOMFormPopulateErrorHandler, which throws an exception and halts on bad or missing values.
	 * 
	 * @return array|false The validated, serialized data on success, or false if validation failed.
	 */
	private function populate(array $data, ?PopulateOptions $options = null): array | false
	{
		$this->resetPopulationErrors();

		$this->populateOptions = $options ?? new PopulateOptions();

		if(array_is_list($data))
			throw new Exception('Expected associative array');

		foreach($data as $key => $value)
		{
			$escaped = addslashes($key);

			$elements = $this
				->element
				->querySelectorAll("[name='$escaped'], [data-name='$escaped']")
				->toArray();

			if(empty($elements))
				throw new NoElementsToPopulateException("Failed to find element named '$key'");
			
			$elements = array_map(function($el) { return new DOMObject($el); }, $elements);
			
			foreach($elements as $target)
			{
				$element = $target[0];
				$name = strtolower($element->nodeName);

				if($element->hasAttribute("required"))
					$this->validateRequired($value, $element);
				
				if($element->hasAttribute("readonly"))
					$this->validateReadonly($value, $element);
				
				if($element->hasAttribute("disabled"))
					$this->validateDisabled($value, $element);

				switch($name)
				{
					case "input":
						$this->populateInput($value, $element);
						break;

					case "select":
						$this->populateSelect($value, $element);
						break;

					case "textarea":
						$target->val($value);
						break;
					
					default:
						$target->text($value);
						break;
				}
			}
		}

		// NB: Handle checkboxes "checked" separately, it works on set / unset
		$this->populateCheckboxes($data);

		// NB: Now handle radios
		$this->populateRadios($data);

		if(count($this->errors) > 0)
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
		$result = [];

		foreach($this->element->querySelectorAll("input[name], select[name], textarea[name]") as $element)
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