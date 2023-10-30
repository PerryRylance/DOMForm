<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PerryRylance\DOMDocument;
use PerryRylance\DOMForm;

class DOMFormBaseTestCase extends TestCase
{
	protected DOMDocument $document;
	protected DOMForm $form;

	protected function instantiateForm(): void
	{
		$this->form = new DOMForm( $this->document->find("form") );
	}

	protected function setUp(): void
	{
		$this->document = new DOMDocument();
		$this->document->load(__DIR__ . "/sample.html");

		$this->instantiateForm();
	}

	protected function getForm(): DOMForm
	{
		return $this->form;
	}

	protected function getRequiredFields(): array
	{
		$requirements	= [];
		$form			= $this->getForm();

		foreach($form->element->querySelectorAll("[name][required]") as $element)
			$requirements[ $element->getAttribute('name') ] = $element->getAttribute('value');
		
		return $requirements;
	}

	// NB: Normally we wouldn't partially populate a form, but we need to in testing. Use this function to include the required fields that we would expect when processing a full submission - eg POST from the browser.
	protected function submitWithRequired(?array $input = [], bool $readback = true): array | false
	{
		$requirements	= $this->getRequiredFields();
		$data			= [...$requirements, ...$input];
		$result			= $this->getForm()->submit($data);

		if($readback)
			foreach(array_keys($input) as $key)
				$this->assertEquals($result[$key], $input[$key]);

		return $result;
	}
}