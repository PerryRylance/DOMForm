# DOMForm
A DOMDocument based form library, useful for quickly populating HTML forms, server side user input validation using HTML5's form validation elements, serialization, and error handling.

The intended use case is for when you have some page that renders a form, either for a new resource or populated with data from an existing resource. When you want to process submissions, you can `submit` the form with the incoming data (eg `$_POST`). This will handle validation. Once you are happy with the resulting data, you can `serialize` that from the form safe in the knowledge that it's been validated client and server side.

Designed for use with [`PerryRylance\DOMDocument`](https://packagist.org/packages/perry-rylance/dom-document).

Earlier versions of `PerryRylance\DOMDocument` had features for populating forms and getting data back, these were dropped in 2.* as that libraries sole focus became jQuery-like PHP DOM manipulation and processing forms was deemed out of scope. This library gives you back this functionality with more standardised behaviour when compared to how the browser's client side validation works.

## Requirements
- PHP >= 8.2
- Composer

## Installation
I recommend installing this package via Composer.

`composer require perry-rylance/dom-form`

## Usage
Here's a very, very basic example, assuming you have a file named `form.html` and have required your autoloader.

### Basic example
```
$document = new DOMFormDocument();
$document->loadHTML('form.html');

$form = $document->find("form");

if(!empty($_POST))
{
	if($data = $form->submit($_POST))
	{
		// NB: $data is validated and ready for use. You can do what you need, for example, store the data and redirect.
	}
	else
	{
		// NB: The data was not validated, you can do what you need, for example display $form in the invalid state.
	}
}

echo $form->html;
```

### Error handling
Error handlers subclass `PerryRylance\DOMForm\Exceptions\Handlers\Handler` and effect how population errors are handled.

You can pass a `Handler` to `DOMForm`'s constructor. If you don't supply one, then the default is `ThrowException` which will throw an exception when validation fails during form population.

`DisplayHtml` is also provided for your convenience, which will add a HTML error message to the relevant field(s) on which validation has failed. You can use this to re-present to form in it's invalid state to the end user so they can correct their input.

## Testing
Docker is required to run tests.

Tests can be run using `tests.sh`.

You can run specific tests from your native CLI like so:

`docker-compose run php82 php /app/vendor/bin/phpunit tests --filter=testCannotAlterDisabledInput`

## Documentation
The requirements to generate documentation are as follows:

- `phpDocumentor` must be [installed](https://docs.phpdoc.org/guide/getting-started/installing.html#installation).

To generate documentation, use the following command.

`php <path/to/your/phpDocumentor.phar> -t ./docs --ignore vendor/`

## Support
Please feel free to open issues here or submit pull requests.