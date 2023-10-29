# DOMForm
A DOMDocument based form library, useful for quickly populating HTML forms, server side user input validation using HTML5's form validation elements, serialization, and error handling.

Designed for use with [`PerryRylance\DOMDocument`](https://packagist.org/packages/perry-rylance/dom-document).

Earlier versions of `PerryRylance\DOMDocument` had features for populating forms and getting data back, these were dropped in 2.* as that libraries sole focus became jQuery-like PHP DOM manipulation and processing forms was deemed out of scope. This library gives you back this functionality with more standardised behaviour when compared to how the browser's client side validation works.

## Requirements
- PHP >= 7.4.0
- Composer

## Installation
I recommend installing this package via Composer.

`composer require perry-rylance/dom-form`

## Documentation
The requirements to generate documentation are as follows:

- `phpDocumentor` must be [installed](https://docs.phpdoc.org/guide/getting-started/installing.html#installation).

To generate documentation, use the following command.

`php <path/to/your/phpDocumentor.phar> -t ./docs --ignore vendor/`

## Support
Please feel free to open issues here or submit pull requests.