# WishBox RetailCRM

Joomla package that installs a shared RetailCRM library and a system plugin with connection settings for Wishbox integrations.

## Requirements

- PHP 8.5 or later;
- Joomla 6.1.4 or later;
- RetailCRM PHP SDK available through `JPATH_SITE/vendor/autoload.php`;
- Composer for development and tests;
- Phing for building the installable package.

## Settings

After installation, open `System - Wishbox RetailCRM` in the Joomla plugin manager and configure:

- RetailCRM API URL or proxy URL;
- RetailCRM API key;
- optional RetailCRM proxy token;
- RetailCRM site code.

Other Joomla extensions can register the shared configuration and API client in their DI container:

```php
use RetailCrm\Api\Client;
use WishboxRetailCrmLibrary\Service\Provider\RetailCrmServiceProvider;

$container->registerServiceProvider(new RetailCrmServiceProvider());
$retailCrmClient = $container->get(Client::class);
```

The client is shared within the current extension container and is created from the system plugin settings.

The package installer enables the plugin automatically on its first installation.

## Tests

```shell
composer install
vendor/bin/phpunit
```

## Build

```shell
php phing-latest.phar
```

The command creates `pkg_wishboxretailcrm.zip`, which can be installed through the Joomla extension installer.
