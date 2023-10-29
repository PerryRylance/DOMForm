<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PerryRylance\DOMDocument\DOMObject;

use PerryRylance\DOMFormDocument;
use PerryRylance\DOMForm\DOMFormElement;
use PerryRylance\DOMForm\Exceptions\BadValueException;
use PerryRylance\DOMForm\Exceptions\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\DatetimeFormatException;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
use PerryRylance\DOMForm\Exceptions\InvalidRegexException;
use PerryRylance\DOMForm\Exceptions\NoElementsToPopulateException;
use PerryRylance\DOMForm\Exceptions\RadioRequiredException;
use PerryRylance\DOMForm\Exceptions\ReadonlyException;
use PerryRylance\DOMForm\Exceptions\ValueRequiredException;

final class DOMFormTest extends TestCase
{
	protected DOMFormDocument $document;

	protected function setUp(): void
	{
		$this->document = new DOMFormDocument();
		$this->document->load(__DIR__ . "/sample.html");
	}

	private function getForm(): DOMFormElement
	{
		$this->assertNotEmpty($this->document->forms);

		return $this->document->forms[0];
	}

	private function getRequiredFields(): array
	{
		$requirements	= [];
		$form			= $this->getForm();

		foreach($form->querySelectorAll("[name][required]") as $element)
			$requirements[ $element->getAttribute('name') ] = $element->getAttribute('value');
		
		return $requirements;
	}

	// NB: Normally we wouldn't partially populate a form, but we need to in testing. Use this function to include the required fields that we would expect when processing a full submission - eg POST from the browser.
	private function populateWithRequired(?array $input = []): void
	{
		$requirements	= $this->getRequiredFields();
		$data			= [...$requirements, ...$input];

		$this->getForm()->populate($data);
	}

	public function testFormIsDomFormElement(): void
	{
		$form = $this->getForm();

		$this->assertInstanceOf(DOMFormElement::class, $form);
	}

	public function testCannotPopulateNonFormElement(): void
	{
		$this->expectException(ElementNotFormException::class);

		$labels = $this->document->find("label");

		$this->assertNotEmpty($labels);

		$labels[0]->populate([]);
	}

	public function testPopulateWithInvalidData(): void
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('Lion', $data['animal']);
	}

	public function testCannotMakeRequiredInputEmpty(): void
	{
		$this->expectException(ValueRequiredException::class);

		$form = $this->getForm();

		$form->populate([
			'required' => ''
		]);
	}

	private function testThrowsReadonlyException(array $data): void
	{
		$this->expectException(ReadonlyException::class);

		$form = $this->getForm();

		$form->populate($data);
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('test', $data['hidden']);
	}

	private function testThrowsBadValueException(array $data): void
	{
		$this->expectException(BadValueException::class);

		$this->populateWithRequired($data);
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
		$this->testPopulateWithInvalidData([
			'numeric' => 64
		]);
	}

	public function testCannotPopulateRangeUnderMin(): void
	{
		$this->testThrowsBadValueException([
			'range' => PHP_INT_MIN
		]);
	}

	public function testCannotPopulateRangeOverMax(): void
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('tigers', $data['favourite-animal']);
	}

	public function testCannotSubmitUncheckedRequiredRadio(): void
	{
		$this->expectException(RadioRequiredException::class);

		$data = $this->getRequiredFields();

		unset($data['favourite-car']);

		$this->getForm()->populate($data);
	}

	public function testRadioUncheckedAfterValueUpdated(): void
	{
		$this->populateWithRequired([
			'favourite-car' => 'ford'
		]);

		$radios = $this->getForm()->querySelectorAll("radio[name='favourite-car'][checked]");

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

		$data = $this->getForm()->serialize();

		$this->assertEquals('2023-11-10T09:00', $data['datetime-local']);
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('2023-10', $data['month']);
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('2013-W29', $data['week']);
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

		$input = $form->querySelector("input[name='postcode']");
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

		$data = $this->getForm()->serialize();

		$this->assertEquals('bears', $data['select']);
	}

	public function testSelectedOptionExclusivity(): void
	{
		$this->populateWithRequired([
			'select-with-selected-option' => 'turnips'
		]);

		$options = $this->getForm()->querySelectorAll("select[name='select-with-selected-option'] > options");

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

		$data = $this->getForm()->serialize();

		$this->assertEquals('modified', $data['textarea']);
	}

	public function testNonFormElement(): void
	{
		$this->populateWithRequired([
			'populated-span' => 'test'
		]);

		$span = new DOMObject( $this->getForm()->querySelector("[data-name='populated-span']") );

		$this->assertEquals('test', $span->text());
	}
}