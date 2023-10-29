# DOMForm
A DOMDocument based form library, useful for quickly populating HTML forms, server side user input validation using HTML5's form validation elements, serialization, and error handling.

The intended use case is for when you have some page that renders a form, either for a new resource or populated with data from an existing resource. When you want to process submissions, you can `populate` the form with the incoming data (eg `$_POST`). This will handle validation. Once you are happy with the resulting data, you can `serialize` that from the form safe in the knowledge that it's been validated client and server side.

Designed for use with [`PerryRylance\DOMDocument`](https://packagist.org/packages/perry-rylance/dom-document).

Earlier versions of `PerryRylance\DOMDocument` had features for populating forms and getting data back, these were dropped in 2.* as that libraries sole focus became jQuery-like PHP DOM manipulation and processing forms was deemed out of scope. This library gives you back this functionality with more standardised behaviour when compared to how the browser's client side validation works.

## Requirements
- PHP >= 8.1
- Composer

## Installation
I recommend installing this package via Composer.

`composer require perry-rylance/dom-form`

## Usage
Here's a very, very basic example, assuming you have a file named `form.html` and have required your autoloader.

### Basic example
```
$document = new DOMFormDocument();
$form = $document->find("form");

if(!empty($_POST))
{
	$form->populate($_POST);
	
	$data = $form->serialize();

	// TODO: Do whatever you need with the validated data
}
```

## Documentation
The requirements to generate documentation are as follows:

- `phpDocumentor` must be [installed](https://docs.phpdoc.org/guide/getting-started/installing.html#installation).

To generate documentation, use the following command.

`php <path/to/your/phpDocumentor.phar> -t ./docs --ignore vendor/`

## Support
Please feel free to open issues here or submit pull requests.