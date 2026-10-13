<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Service\Provider;

use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use RetailCrm\Api\Client;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;
use WishboxRetailCrmLibrary\Service\RetailCrmProductService;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registers the RetailCRM product service.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final class RetailCrmProductServiceProvider implements ServiceProviderInterface
{
	/**
	 * Register the RetailCRM product service.
	 *
	 * @param   Container  $container  Dependency injection container.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function register(Container $container): void
	{
		$container->share(
			RetailCrmProductService::class,
			static fn (Container $container): RetailCrmProductService => new RetailCrmProductService(
				$container->get(Client::class),
				$container->get(RetailCrmConfiguration::class)
			)
		);
	}
}
