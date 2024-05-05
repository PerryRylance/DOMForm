<?php declare(strict_types=1);

require_once __DIR__ . '/DOMFormBaseTestCase.php';

use PerryRylance\DOMDocument\DOMObject;

use PerryRylance\DOMForm;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\InvalidRegexException;
use PerryRylance\DOMForm\Exceptions\Population\BadValueException;
use PerryRylance\DOMForm\Exceptions\Population\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\DatetimeFormatException;
use PerryRylance\DOMForm\Exceptions\Population\DisabledException;
use PerryRylance\DOMForm\Exceptions\Population\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\Population\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\Population\ReadonlyException;
use PerryRylance\DOMForm\Exceptions\Population\ValueRequiredException;

final class DOMFormTest extends DOMFormBaseTestCase
{
	private function testThrowsBadValueException(array $data): void
	{
		$this->expectException(BadValueException::class);

		$this->submitWithRequired($data);
	}

	private function testThrowsReadonlyException(array $data): void
	{
		$this->expectException(ReadonlyException::class);

		$this->form->submit($data);
	}

	public function testCannotInstantiateOnNonFormElement(): void
	{
		$this->expectException(ElementNotFormException::class);

		$labels = $this->document->find("label");

		$this->assertNotEmpty($labels);

		new DOMForm($labels[0]);
	}

	public function testCannotSubmitNonExistantField(): void
	{
		$this->expectException(NoElementsToPopulateException::class);

		$this->submitWithRequired([
			'input-which-does-not-exist' => 123
		]);
	}

	public function testPopulateNamedInput(): void
	{
		$this->submitWithRequired([
			'animal' => 'Lion'
		]);
	}

	public function testCannotMakeRequiredInputEmpty(): void
	{
		$this->expectException(ValueRequiredException::class);

		$this->submitWithRequired([
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
		$this->expectException(DisabledException::class);

		$this->form->submit([
			'disabled' => 'test'
		]);
	}

	public function testReadWriteHiddenElement(): void
	{
		$this->submitWithRequired([
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'numeric' => 64
		]);
	}

	public function testCannotPopulateRangeUnderMinimum(): void
	{
		$this->testThrowsBadValueException([
			'range' => PHP_INT_MIN + 1
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'color' => '#00ff00'
		]);
	}

	public function testCanCheckCheckbox(): void
	{
		$this->submitWithRequired([
			'checkbox' => 'checked'
		]);
		
		$data = $this->form->serialize();

		$this->assertTrue(isset($data['checkbox']));
		$this->assertEquals('checked', $data['checkbox']);
	}

	public function testCanUncheckCheckbox(): void
	{
		$this->submitWithRequired();

		$data = $this->form->serialize();

		$this->assertTrue(!isset($data['checkbox']));
	}

	public function testCannotSubmitUncheckRequiredCheckbox(): void
	{
		$this->expectException(CheckboxRequiredException::class);

		$fields = $this->getRequiredFields();

		unset($fields['required-checkbox']);

		$this->form->submit($fields);
	}

	public function testDefaultCheckedRadioHasExpectedValue(): void
	{
		$data = $this->form->serialize();

		$this->assertEquals('mercedes', $data['favourite-car']);
	}

	public function testCanCheckRadio(): void
	{
		$this->submitWithRequired([
			'favourite-animal' => 'tigers'
		]);
	}

	public function testCannotSubmitUncheckedRequiredRadio(): void
	{
		$this->expectException(RadioRequiredException::class);

		$fields = $this->getRequiredFields();

		unset($fields['favourite-car']);

		$this->form->submit($fields);
	}

	public function testRadioUncheckedAfterValueUpdated(): void
	{
		$this->submitWithRequired([
			'favourite-car' => 'ford'
		]);

		$radios = $this->form->element->querySelectorAll("radio[name='favourite-car'][checked]");

		foreach($radios as $radio)
			$this->assertEquals('ford', $radio->getAttribute('value'));
	}

	public function testNumericInputWithMinimum(): void
	{
		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'maximum' => PHP_INT_MIN + 1
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'datetime-local' => '2023-11-10T09:00'
		]);
	}
	
	public function testCannotPopulateInvalidDatetime(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'month' => '2023-10'
		]);
	}

	public function testCannotPopulateInvalidMonth(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'week' => '2013-W29'
		]);
	}

	public function testCannotPopulateInvalidWeek(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->submitWithRequired([
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
		$this->submitWithRequired([
			'time' => '14:00'
		]);
	}

	public function testCannotPopulateInvalidTime(): void
	{
		$this->expectException(DatetimeFormatException::class);

		$this->submitWithRequired([
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
		$this->submitWithRequired([
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
		$form = $this->form;

		$input = $form->element->querySelector("input[name='postcode']");
		$input->setAttribute('pattern', '[[[Definitely not valid regex/');

		$this->expectException(InvalidRegexException::class);
		
		$this->testCanPopulateInputWithPattern();
	}

	public function testExpectedDefaultSelectOption(): void
	{
		$data = $this->form->serialize();

		$this->assertEquals('lions', $data['select']);
	}

	public function testExpectedDefaultSelectWithImplicitValues(): void
	{
		$data = $this->form->serialize();

		$this->assertEquals('Jazz', $data['select-with-implicit-values']);
	}
	
	public function testOptionSelection(): void
	{
		$this->submitWithRequired([
			'select' => 'bears'
		]);
	}

	public function testSelectedOptionExclusivity(): void
	{
		$this->submitWithRequired([
			'select-with-selected-option' => 'turnips'
		]);

		$options = $this
			->form
			->element
			->querySelectorAll("select[name='select-with-selected-option'] > options");

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
		$this->submitWithRequired([
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
		$data = $this->form->serialize();

		$this->assertIsArray($data['multi-select[]']);
		$this->assertEmpty($data['multi-select[]']);
	}

	public function testSelectMultipleOptions(): void
	{
		$this->submitWithRequired([
			'multi-select[]' => [
				'ford',
				'iveco'
			]
		]);

		$data = $this->form->serialize();

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
		$this->submitWithRequired([
			'textarea' => 'modified'
		]);
	}

	public function testNonFormElement(): void
	{
		$this->submitWithRequired([
			'populated-span' => 'test'
		], false);

		$span = new DOMObject( $this->form->element->querySelector("[data-name='populated-span']") );

		$this->assertEquals('test', $span->text());
	}

	public function testPrePopulate(): void
	{
		// NB: Grab the state from the existing form, then make a new form and populate that with the state. This emulates initially populating a form from a data source such as a database.
		$form = new DOMForm($this->document->find("form"), $this->form->serialize());

		$this->assertEquals($this->form->serialize(), $form->serialize());
	}

	public function testSubmitReturnsData(): void
	{
		$result = $this->submitWithRequired();

		$this->assertIsArray($result);
	}
}