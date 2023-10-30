<?php declare(strict_types=1);

require_once __DIR__ . '/DOMFormBaseTestCase.php';

use PerryRylance\DOMForm;
use PerryRylance\DOMForm\Exceptions\Handlers\DisplayHtml;

class DOMFormPopulateDisplayHtmlErrorHandlerTest extends DOMFormBaseTestCase
{
	protected function instantiateForm(): void
	{
		$this->form = new DOMForm(
			$this->document->find("form"),
			new DisplayHtml()
		);
	}

	protected function populateWithRequired(?array $input = [], bool $readback = true): void
	{
		// NB: Don't readback by default. We are testing specifically for cases where errors are displayed, so it's expected that DOMForm::serialize will return false
		Parent::populateWithRequired($input, false);
	}

	protected function assertErrorAfter(string $name): void
	{
		$escaped	= addslashes($name);
		$span		= $this
			->getForm()
			->element
			->querySelector("[name='$escaped'] ~ span.error");

		$this->assertNotNull($span);
	}

	public function testDisplaysRequiredErrorMessage(): void
	{
		$this->populateWithRequired([
			'required' => ''
		]);
		
		$this->assertErrorAfter('required');
	}

	public function testDisplaysBadUrlMessage(): void
	{
		$this->populateWithRequired([
			'url' => '*** Definitely not a valid URL ***'
		]);

		$this->assertErrorAfter('url');
	}

	public function testDisplaysInvalidEmailMessage(): void
	{
		$this->populateWithRequired([
			'email' => '*** Definitely not a valid email address ***'
		]);

		$this->assertErrorAfter('email');
	}

	public function testDisplaysInvalidMultipleEmailMessage(): void
	{
		$this->populateWithRequired([
			'bcc' => 'valid@email.com,*** Definitely not a valid email address ***'
		]);

		$this->assertErrorAfter('bcc');
	}

	public function testDisplaysInvalidNumberMessage(): void
	{
		$this->populateWithRequired([
			'numeric' => 'string'
		]);

		$this->assertErrorAfter('numeric');
	}

	public function testDisplaysRangeUnderMinimumMessage(): void
	{
		$this->populateWithRequired([
			'range' => PHP_INT_MIN
		]);

		$this->assertErrorAfter('range');
	}

	public function testDisplaysRangeUnderMaximumMessage(): void
	{
		$this->populateWithRequired([
			'range' => PHP_INT_MAX
		]);

		$this->assertErrorAfter('range');
	}

	public function testDisplaysInvalidColorMessage(): void
	{
		$this->populateWithRequired([
			'color' => 'invalid color'
		]);

		$this->assertErrorAfter('color');
	}

	public function testDisplaysCheckboxRequiredMessage(): void
	{
		$fields = $this->getRequiredFields();

		unset($fields['required-checkbox']);

		$this->getForm()->populate($fields);

		$this->assertErrorAfter('required-checkbox');
	}

	public function testDisplaysRadioRequiredMessage(): void
	{
		$form = $this->getForm();
		$fields = $this->getRequiredFields();

		unset($fields['favourite-car']);

		$form->populate($fields);

		$span = $form
			->element
			->querySelector("[name='favourite-car'] ~ span.error");

		$this->assertNotNull($span);
	}

	public function testDisplaysBelowMinimumMessage(): void
	{
		$this->populateWithRequired([
			'minimum' => 0
		]);

		$this->assertErrorAfter('minimum');
	}

	public function testDisplaysAboveMaximumMessage(): void
	{
		$this->populateWithRequired([
			'maximum' => 12345
		]);

		$this->assertErrorAfter('maximum');
	}

	public function testDisplaysOutOfSequenceMessageForInteger(): void
	{
		$this->populateWithRequired([
			'stepped-integer' => 5
		]);

		$this->assertErrorAfter('stepped-integer');
	}

	public function testDisplaysOutOfSequenceMessageForFloat(): void
	{
		$this->populateWithRequired([
			'stepped-float' => 3.1415926
		]);
		
		$this->assertErrorAfter('stepped-float');
	}

	public function testDisplaysInvalidDatetimeMessage(): void
	{
		$this->populateWithRequired([
			'datetime-local' => 'Definitely not a valid datetime'
		]);

		$this->assertErrorAfter('datetime-local');
	}

	public function testDisplaysDatetimeBelowMinimumMessage(): void
	{
		$this->populateWithRequired([
			'datetime-local-with-min' => '2018-06-06T00:00'
		]);

		$this->assertErrorAfter('datetime-local-with-min');
	}

	public function testDisplaysDatetimeAboveMaximumMessage(): void
	{
		$this->populateWithRequired([
			'datetime-local-with-max' => '2018-06-15T00:00'
		]);

		$this->assertErrorAfter('datetime-local-with-max');
	}

	public function testDisplaysInvalidMonthMessage(): void
	{
		$this->populateWithRequired([
			'month' => 'An invalid month'
		]);

		$this->assertErrorAfter('month');
	}

	public function testDisplaysMonthBelowMinimumMessage(): void
	{
		$this->populateWithRequired([
			'month-with-min' => '2018-02'
		]);

		$this->assertErrorAfter('month-with-min');
	}

	public function testDisplaysMonthAboveMaximumMessage(): void
	{
		$this->populateWithRequired([
			'month-with-max' => '2018-08'
		]);
		
		$this->assertErrorAfter('month-with-max');
	}

	public function testDisplaysInvalidWeekMessage(): void
	{
		$this->populateWithRequired([
			'week' => 'Definitely an invalid week'
		]);

		$this->assertErrorAfter('week');
	}

	public function testDisplaysWeekBelowMinimumMessage(): void
	{
		$this->populateWithRequired([
			'week' => '2013-W27'
		]);

		$this->assertErrorAfter('week');
	}

	public function testDisplaysWeekAboveMaximumMessage(): void
	{
		$this->populateWithRequired([
			'week' => '2013-W33'
		]);

		$this->assertErrorAfter('week');
	}

	public function testDisplaysInvalidTimeMessage(): void
	{
		$this->populateWithRequired([
			'time' => 'Invalid'
		]);

		$this->assertErrorAfter('time');
	}

	public function testDisplaysTimeBelowMinimumMessage(): void
	{
		$this->populateWithRequired([
			'time' => '07:00'
		]);

		$this->assertErrorAfter('time');
	}

	public function testDisplaysTimeAboveMaximumMessage(): void
	{
		$this->populateWithRequired([
			'time' => '18:00'
		]);

		$this->assertErrorAfter('time');
	}

	public function testDisplaysPatternDoesNotMatchMessage(): void
	{
		$this->populateWithRequired([
			'postcode' => 'This doesn\'t match the specified pattern'
		]);

		$this->assertErrorAfter('postcode');
	}

	public function testDisplaysInvalidOptionsMessage(): void
	{
		$this->populateWithRequired([
			'select' => 'Not a valid option'
		]);

		$this->assertErrorAfter('select');
	}

	public function testDisplaysInvalidMultiSelectOptionMessage(): void
	{
		$this->populateWithRequired([
			'multi-select[]' => [
				'not a valid option'
			]
		]);

		$this->assertErrorAfter('multi-select[]');
	}
}