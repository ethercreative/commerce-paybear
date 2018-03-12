# Craft Commerce - PayBear.io  
PayBear.io integration with Craft Commerce.

## Usage

1. Install
2. Create a PayBear payment method in Commerce
3. Put the ID of the payment method in `craft/config/commercepaybear.php` (copy from [`config.php`](https://github.com/ethercreative/commerce-paybear/blob/master/commercepaybear/config.php) in the plugins folder).
4. Add the following code into your checkout payment page:
```twig
{{ craft.commercepaybear.embed({
	button: '#idOfButton',
	modal: true,
	redirect: 'URL to redirect to on completion',
	timer: 15 * 60,
}) }}
```
See https://github.com/Paybear/paybear-samples/tree/master/form#advanced-usage for more options.

Disable CSRF for the callback URL:
```php
<?php

return [	
	// ...

	'enableCsrfProtection' => (
		!isset($_SERVER['REQUEST_URI']) ||
		(
			!strpos($_SERVER['REQUEST_URI'], 'CommercePayBear/callback')
		)
	),
];
``` 