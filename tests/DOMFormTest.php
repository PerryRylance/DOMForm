<?php declare(strict_types=1);

require_once __DIR__ . '/DOMFormBaseTestCase.php';

use PerryRylance\DOMDocument\DOMObject;

use PerryRylance\DOMForm;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\InvalidRegexException;
use PerryRylance\DOMForm\Exceptions\Population\BadValueException;
use PerryRylance\DOMForm\Exceptions\Population\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\DatetimeFormatException;
use PerryRylance\DOMForm\Exceptions\Population\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\Population\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\ReadonlyException;
use PerryRylance\DOMForm\Exceptions\Population\ValueRequiredException;

final class DOMFormTest extends DOMFormBaseTestCase
{
	private function testThrowsBadValueException(array $data): void
	{
		$this->expectException(BadValueException::class);

		$this->populateWithRequired($data);
	}

	private function testThrowsReadonlyException(array $data): void
	{
		$this->expectException(ReadonlyException::class);

		$form = $this->getForm();

		$form->populate($data);
	}

	public function testCannotInstantiateOnNonFormElement(): void
	{
		$this->expectException(ElementNotFormException::class);

		$labels = $this->document->find("label");

		$this->assertNotEmpty($labels);

		new DOMForm($labels[0]);
	}

	public function testCannotPopulateNonExistantField(): void
	{
		$this->expectException(NoElementsToPopulateException::class);

		$this->populateWithRequired([
			'input-which-does-not-exist' => 123
		]);
	}

	public function testPopulateNamedInput(): void
	{
		$this->populateWithRequired([
			'animal' => 'Lion'
		]);
	}

	public function testCannotMakeRequiredInputEmpty(): void
	{
		$this->expectException(ValueRequiredException::class);

		$this->populateWithRequired([
			'required' => ''
		]);
	}

	public function testCannotAlterReadonlyInput(): void
	{
		$this->testThrowsReadonlyException([
			'readonly' => 'test'
		]);
	}

	public function testCannotAlterDisabledInput(): void
	{
		$this->testThrowsReadonlyException([
			'disabled' => 'test'
		]);
	}

	public function testReadWriteHiddenElement(): void
	{
		$this->populateWithRequired([
			'hidden' => 'test'
		]);
	}

	public function testCannotPopulateWithInvalidUrl(): void
	{
		$this->testThrowsBadValueException([
			'url' => '*** Definitely not a valid URL ***'
		]);
	}

	public function testCanPopulateWithValidUrl(): void
	{
		$this->populateWithRequired([
			'url' => 'https://youtube.com'
		]);
	}

	public function testCannotPopulateWithInvalidEmail(): void
	{
		$this->testThrowsBadValueException([
			'email' => '*** Definitely not a valid email address ***'
		]);
	}

	public function testCanPopulateWithValidEmail(): void
	{
		$this->populateWithRequired([
			'email' => 'test@perryrylance.com'
		]);
	}

	public function testCannotPopulateWithMultipleInvalidEmail(): void
	{
		$this->testThrowsBadValueException([
			'bcc' => 'valid@email.com,*** Definitely not a valid email address ***'
		]);
	}

	public function testCanPopulateWithMultipleValidEmails(): void
	{
		$this->populateWithRequired([
			'bcc' => 'test@perryrylance.com,valid@email.com'
		]);
	}

	public function testCannotPopulateWithInvalidNumber(): void
	{
		$this->testThrowsBadValueException([
			'numeric' => 'string'
		]);
	}

	public function testCanPopulateWithValidNumber(): void
	{
		$this->populateWithRequired([
			'numeric' => 64
		]);
	}

	public function testCannotPopulateRangeUnderMinimum(): void
	{
		$this->testThrowsBadValueException([
			'range' => PHP_INT_MIN
		]);
	}

	public function testCannotPopulateRangeOverMaximum(): void
	{
		$this->testThrowsBadValueException([
			'range' => PHP_INT_MAX
		]);
	}

	public function testCanPopulateRange(): void
	{
		$this->populateWithRequired([
			'range' => 100000
		]);
	}

	public function testCannotPopulateInvalidColor(): void
	{
		$this->testThrowsBadValueException([
			'color' => 'invalid color'
		]);
	}

	public function testCanPopulateValidColor(): void
	{
		$this->populateWithRequired([
			'color' => '#00ff00'
		]);
	}

	public function testCanCheckCheckbox(): void
	{
		$this->populateWithRequired([
			'checkbox' => 'checked'
		]);
		
		$data = $this->getForm()->serialize();

		$this->assertTrue(isset($data['checkbox']));
		$this->assertEquals('checked', $data['checkbox']);
	}

	public function testCanUncheckCheckbox(): void
	{
		$this->populateWithRequired();

		$data = $this->getForm()->serialize();

		$this->assertTrue(!isset($data['checkbox']));
	}

	public function testCannotSubmitUncheckRequiredCheckbox(): void
	{
		$this->expectException(CheckboxRequiredException::class);

		$fields = $this->getRequiredFields();

		unset($fields['required-checkbox']);

		$this->getForm()->populate($fields);
	}

	public function testDefaultCheckedRadioHasExpectedValue(): void
	{
		$data = $this->getForm()->serialize();

		$this->assertEquals('mercedes', $data['favourite-car']);
	}

	public function testCanCheckRadio(): void
	{
		$this->populateWithRequired([
			'favourite-animal' => 'tigers'
		]);
	}

	public function testCannotSubmitUncheckedRequiredRadio(): void
	{
		$this->expectException(RadioRequiredException::class);

		$fields = $this->getRequiredFields();

		unset($fields['favourite-car']);

		$this->getForm()->populate($fields);
	}

	public function testRadioUncheckedAfterValueUpdated(): void
	{
		$this->populateWithRequired([
			'favourite-car' => 'ford'
		]);

		$radios = $this->getForm()->element->querySelectorAll("radio[name='favourite-car'][checked]");

		foreach($radios as $radio)
			$this->assertEquals('ford', $radio->getAttribute('value'));
	}

	public function testNumericInputWithMinimum(): void
	{
		$this->populateWithRequired([
			'minimum' => PHP_INT_MAX
		]);
	}

	public function testCannotPopulateNumericInputBelowMinimum(): void
	{
		$this->testThrowsBadValueException([
			'minimum' => 0
		]);
	}

	public function testNumericInputWithMaximum(): void
	{
		$this->populateWithRequired([
			'maximum' => PHP_INT_MIN
		]);
	}

	public function testCannotPopulateNumericInputAboveMaximum(): void
	{
		$this->testThrowsBadValueException([
			'maximum' => 12345
		]);
	}

	public function testCanPopulateInSequenceInteger(): void
	{
		$this->populateWithRequired([
			'stepped-integer' => 6
		]);
	}

	public function testCannotPopulateOutOfSequenceInteger(): void
	{
		$this->testThrowsBadValueException([
			'stepped-integer' => 5
		]);
	}

	public function testCanPopulateInSequenceFloat(): void
	{
		$this->populateWithRequired([
			'stepped-float' => 1.8
		]);
	}

	public function testCannotPopulateOutOfSequenceFloat(): void
	{
		$this->testThrowsBadValueException([
			'stepped-float' => 3.1415926
		]);
	}

	public function testCanPopulateDatetimeLocal(): void
	{
		$this->populateWithRequired([
			'datetime-local' => '2023-11-10T09:00'
		]);
	}
	
	public function testCannotPopulateInvalidDatetime(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->populateWithRequired([
			'datetime-local' => 'Definitely not a valid datetime'
		]);
	}

	public function testCannotPopulateDatetimeBelowMinimum(): void
	{
		$this->testThrowsBadValueException([
			'datetime-local-with-min' => '2018-06-06T00:00'
		]);
	}

	public function testCannotPopulateDatetimeAboveMaximum(): void
	{
		$this->testThrowsBadValueException([
			'datetime-local-with-max' => '2018-06-15T00:00'
		]);
	}

	public function testCanPopulateMonth(): void
	{
		$this->populateWithRequired([
			'month' => '2023-10'
		]);
	}

	public function testCannotPopulateInvalidMonth(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->populateWithRequired([
			'month' => 'An invalid month'
		]);
	}

	public function testCannotPopulateMonthBelowMinimum(): void
	{
		$this->testThrowsBadValueException([
			'month-with-min' => '2018-02'
		]);
	}

	public function testCannotPopulateMonthAboveMaximum(): void
	{
		$this->testThrowsBadValueException([
			'month-with-max' => '2018-08'
		]);
	}

	public function testCanPopulateWeek(): void
	{
		$this->populateWithRequired([
			'week' => '2013-W29'
		]);
	}

	public function testCannotPopulateInvalidWeek(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->populateWithRequired([
			'week' => 'Definitely an invalid week'
		]);
	}

	public function testCannotPopulateWeekBelowMinimum(): void
	{
		$this->testThrowsBadValueException([
			'week' => '2013-W27'
		]);
	}

	public function testCannotPopulateWeekAboveMaximum(): void
	{
		$this->testThrowsBadValueException([
			'week' => '2013-W33'
		]);
	}

	public function testCanPopulateTime(): void
	{
		$this->populateWithRequired([
			'time' => '14:00'
		]);
	}

	public function testCannotPopulateInvalidTime(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->populateWithRequired([
			'time' => 'Invalid'
		]);
	}

	public function testCannotPopulateTimeBelowMinimum(): void
	{
		$this->testThrowsBadValueException([
			'time' => '07:00'
		]);
	}

	public function testCannotPopulateTimeAboveMaximum(): void
	{
		$this->testThrowsBadValueException([
			'time' => '18:00'
		]);
	}

	public function testCanPopulateInputWithPattern(): void
	{
		$this->populateWithRequired([
			'postcode' => 'AA11 1AA'
		]);
	}

	public function testCannotPopulateInputNotMatchingPattern(): void
	{
		$this->testThrowsBadValueException([
			'postcode' => 'This doesn\'t match the specified pattern'
		]);
	}

	public function testInvalidRegexThrowsException(): void
	{
		$form = $this->getForm();

		$input = $form->element->querySelector("input[name='postcode']");
		$input->setAttribute('pattern', '[[[Definitely not valid regex/');

		$this->expectException(InvalidRegexException::class);
		
		$this->testCanPopulateInputWithPattern();
	}

	public function testExpectedDefaultSelectOption(): void
	{
		$data = $this->getForm()->serialize();

		$this->assertEquals('lions', $data['select']);
	}

	public function testExpectedDefaultSelectWithImplicitValues(): void
	{
		$data = $this->getForm()->serialize();

		$this->assertEquals('Jazz', $data['select-with-implicit-values']);
	}
	
	public function testOptionSelection(): void
	{
		$this->populateWithRequired([
			'select' => 'bears'
		]);
	}

	public function testSelectedOptionExclusivity(): void
	{
		$this->populateWithRequired([
			'select-with-selected-option' => 'turnips'
		]);

		$options = $this->getForm()->element->querySelectorAll("select[name='select-with-selected-option'] > options");

		foreach($options as $option)
		{
			$value = $option->getAttribute('value');

			if($option->hasAttribute('selected'))
				$this->assertEquals('turnips', $value);
			else
				$this->assertNotEquals('turnips', $value);
		}
	}

	// NB: Unsupported in PerryRylance\DOMDocument right now
	/* public function testOptionSelectionByInnerText(): void
	{

	} */

	public function testCannotSelectInvalidOption(): void
	{
		$this->testThrowsBadValueException([
			'select' => 'Not a valid option'
		]);
	}

	public function testSelectOptionWithinOptgroups(): void
	{
		$this->populateWithRequired([
			'select-with-optgroups' => 'carrots'
		]);
	}

	public function testCannotSelectInvalidOptionWithinOptgroups(): void
	{
		$this->testThrowsBadValueException([
			'select-with-optgroups' => 'Not a valid selection'
		]);
	}

	public function testMultiSelectEmptyByDefault(): void
	{
		$data = $this->getForm()->serialize();

		$this->assertIsArray($data['multi-select[]']);
		$this->assertEmpty($data['multi-select[]']);
	}

	public function testSelectMultipleOptions(): void
	{
		$this->populateWithRequired([
			'multi-select[]' => [
				'ford',
				'iveco'
			]
		]);

		$data = $this->getForm()->serialize();

		$this->assertTrue(
			$data['multi-select[]'] === ['ford', 'iveco']
		);
	}

	public function testCannotPopulateMultiSelectWithInvalidOptions(): void
	{
		$this->testThrowsBadValueException([
			'multi-select[]' => [
				'not a valid option'
			]
		]);
	}

	public function testTextarea(): void
	{
		$this->populateWithRequired([
			'textarea' => 'modified'
		]);
	}

	public function testNonFormElement(): void
	{
		$this->populateWithRequired([
			'populated-span' => 'test'
		], false);

		$span = new DOMObject( $this->getForm()->element->querySelector("[data-name='populated-span']") );

		$this->assertEquals('test', $span->text());
	}
}