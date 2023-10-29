<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PerryRylance\DOMFormDocument;
use PerryRylance\DOMForm\DOMFormElement;
use PerryRylance\DOMForm\Exceptions\BadValueException;
use PerryRylance\DOMForm\Exceptions\CheckboxRequiredException;
use PerryRylance\DOMForm\Exceptions\ElementNotFormException;
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

	public function testNumericInputWithMinimum()
	{
		$this->populateWithRequired([
			'minimum' => PHP_INT_MAX
		]);
	}

	public function testCannotPopulateNumericInputBelowMinimum()
	{
		$this->testThrowsBadValueException([
			'minimum' => 0
		]);
	}

	public function testNumericInputWithMaximum()
	{
		$this->populateWithRequired([
			'maximum' => PHP_INT_MIN
		]);
	}

	public function testCannotPopulateNumericInputAboveMaximum()
	{
		$this->testThrowsBadValueException([
			'maximum' => 12345
		]);
	}

	public function testCanPopulateInSequenceInteger()
	{
		$this->populateWithRequired([
			'stepped-integer' => 6
		]);
	}

	public function testCannotPopulateOutOfSequenceInteger()
	{
		$this->testThrowsBadValueException([
			'stepped-integer' => 5
		]);
	}

	public function testCanPopulateInSequenceFloat()
	{
		$this->populateWithRequired([
			'stepped-float' => 1.8
		]);
	}

	public function testCannotPopulateOutOfSequenceFloat()
	{
		$this->testThrowsBadValueException([
			'stepped-float' => 3.1415926
		]);
	}
	
	// TODO: Datetime inputs
	// TODO: Pattern matching inputs
	// TODO: Select and multiple select
	// TODO: Non-input elements

	// TODO: Arrays?
}