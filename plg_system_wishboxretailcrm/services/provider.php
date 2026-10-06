<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\WishboxRetailCrm\Extension\WishboxRetailCrm;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides the Wishbox RetailCRM system plugin.
 *
 * @since 1.0.0
 */
return new class implements ServiceProviderInterface {
	/**
	 * Register the plugin in Joomla's dependency injection container.
	 *
	 * @param Container $container Dependency injection container.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			static function (Container $container): PluginInterface {
				$config = (array) PluginHelper::getPlugin('system', 'wishboxretailcrm');
				$plugin = new WishboxRetailCrm(
					$container->get(DispatcherInterface::class),
					$config
				);

				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
