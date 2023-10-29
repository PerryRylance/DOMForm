<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PerryRylance\DOMFormDocument;
use PerryRylance\DOMForm\DOMFormElement;
use PerryRylance\DOMForm\Handlers\DOMFormPopulateErrorHandler;

class DOMFormBaseTestCase extends TestCase
{
	protected DOMFormDocument $document;

	public static function setUpBeforeClass(): void
	{
		DOMFormElement::$defaultPopulateErrorHandler = new DOMFormPopulateErrorHandler();
	}

	protected function setUp(): void
	{
		$this->document = new DOMFormDocument();
		$this->document->load(__DIR__ . "/sample.html");
	}

	protected function getForm(): DOMFormElement
	{
		$this->assertNotEmpty($this->document->forms);

		return $this->document->forms[0];
	}

	protected function getRequiredFields(): array
	{
		$requirements	= [];
		$form			= $this->getForm();

		foreach($form->querySelectorAll("[name][required]") as $element)
			$requirements[ $element->getAttribute('name') ] = $element->getAttribute('value');
		
		return $requirements;
	}

	// NB: Normally we wouldn't partially populate a form, but we need to in testing. Use this function to include the required fields that we would expect when processing a full submission - eg POST from the browser.
	protected function populateWithRequired(?array $input = []): void
	{
		$requirements	= $this->getRequiredFields();
		$data			= [...$requirements, ...$input];

		$this->getForm()->populate($data);
	}
}