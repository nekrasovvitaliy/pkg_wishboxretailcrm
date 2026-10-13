<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Service\Provider;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;
use RetailCrm\Api\Client;
use RuntimeException;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;
use WishboxRetailCrmLibrary\Factory\RetailCrmClientFactory;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registers the shared RetailCRM configuration and API client.
 *
 * @since 1.0.0
 */
final class RetailCrmServiceProvider implements ServiceProviderInterface
{
	/**
	 * Register shared RetailCRM services in an extension container.
	 *
	 * @param   Container  $container  Dependency injection container.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function register(Container $container): void
	{
		$composerAutoloadPath = JPATH_SITE . '/vendor/autoload.php';

		if (!is_file($composerAutoloadPath))
		{
			throw new RuntimeException('The site Composer autoloader is required to load the RetailCRM PHP SDK.');
		}

		require_once $composerAutoloadPath;

		if (!class_exists(Client::class))
		{
			throw new RuntimeException('The RetailCRM PHP SDK must be installed before using WishBox RetailCRM.');
		}

		$container->share(
			RetailCrmConfiguration::class,
			static function (): RetailCrmConfiguration {
				$plugin = (array) PluginHelper::getPlugin('system', 'wishboxretailcrm');
				$params = new Registry($plugin['params'] ?? '');

				return new RetailCrmConfiguration(
					trim((string) $params->get('retailcrm_api_url')),
					trim((string) $params->get('retailcrm_api_key')),
					trim((string) $params->get('retailcrm_proxy_token')),
					trim((string) $params->get('retailcrm_site'))
				);
			}
		);

		$container->share(
			Client::class,
			static function (Container $container): Client {
				$retailCrmConfiguration = $container->get(RetailCrmConfiguration::class);

				if ($retailCrmConfiguration->apiUrl === '' || $retailCrmConfiguration->apiKey === '')
				{
					throw new RuntimeException(
						'RetailCRM API URL and API key must be configured in the WishBox RetailCRM system plugin.'
					);
				}

				return RetailCrmClientFactory::createClient(
					$retailCrmConfiguration->apiUrl,
					$retailCrmConfiguration->apiKey,
					$retailCrmConfiguration->proxyToken
				);
			}
		);
	}
}
