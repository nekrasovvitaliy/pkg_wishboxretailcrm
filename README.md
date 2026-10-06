# WishBox RetailCRM

Joomla package that installs a system plugin with shared RetailCRM connection settings for Wishbox integrations.

## Requirements

- PHP 8.5 or later;
- Joomla 6.1.4 or later;
- Composer for development and tests;
- Phing for building the installable package.

## Settings

After installation, open `System - Wishbox RetailCRM` in the Joomla plugin manager and configure:

- RetailCRM API URL or proxy URL;
- RetailCRM API key;
- RetailCRM site code.

Other Joomla extensions can load the shared settings with:

```php
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

$plugin = PluginHelper::getPlugin('system', 'wishboxretailcrm');
$wishboxRetailCrmPluginParams = new Registry($plugin->params ?? '');
```

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
