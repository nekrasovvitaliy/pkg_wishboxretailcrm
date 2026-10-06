<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides the Wishbox RetailCRM plugin installer script.
 *
 * @since 1.0.0
 */
return new class implements ServiceProviderInterface {
	/**
	 * Register the plugin installer script.
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
		/**
		 * Enables the system plugin after its initial installation.
		 *
		 * @since 1.0.0
		 */
		$installerScript = new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
			/**
			 * Initialise the plugin installer script.
			 *
			 * @param DatabaseInterface $database Joomla database connection.
			 *
			 * @return void
			 *
			 * @since 1.0.0
			 */
			public function __construct(private readonly DatabaseInterface $database)
			{
			}

			/**
			 * Enable the plugin after installation.
			 *
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return boolean
			 *
			 * @since 1.0.0
			 *
			 * @noinspection PhpUnused
			 */
			public function install(InstallerAdapter $adapter): bool
			{
				$this->enablePlugin($adapter);

				return true;
			}

			/**
			 * Handle plugin update.
			 *
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return boolean
			 *
			 * @since 1.0.0
			 *
			 * @noinspection PhpUnused
			 * @noinspection PhpUnusedParameterInspection
			 */
			public function update(InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Handle plugin removal.
			 *
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return boolean
			 *
			 * @since 1.0.0
			 *
			 * @noinspection PhpUnused
			 * @noinspection PhpUnusedParameterInspection
			 */
			public function uninstall(InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Handle preflight checks.
			 *
			 * @param string           $type    Installer operation type.
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return boolean
			 *
			 * @since 1.0.0
			 *
			 * @noinspection PhpUnused
			 * @noinspection PhpUnusedParameterInspection
			 */
			public function preflight(string $type, InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Handle postflight tasks.
			 *
			 * @param string           $type    Installer operation type.
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return boolean
			 *
			 * @since 1.0.0
			 *
			 * @noinspection PhpUnused
			 * @noinspection PhpUnusedParameterInspection
			 */
			public function postflight(string $type, InstallerAdapter $adapter): bool
			{
				return true;
			}

			/**
			 * Enable the installed plugin extension record.
			 *
			 * @param InstallerAdapter $adapter Installer adapter.
			 *
			 * @return void
			 *
			 * @since 1.0.0
			 */
			private function enablePlugin(InstallerAdapter $adapter): void
			{
				$plugin = (object) [
					'type'    => 'plugin',
					'element' => $adapter->getElement(),
					'folder'  => (string) $adapter->getParent()->manifest->attributes()['group'],
					'enabled' => 1,
				];

				$this->database->updateObject(
					'#__extensions',
					$plugin,
					['type', 'element', 'folder']
				);
			}
		};

		$container->set(InstallerScriptInterface::class, $installerScript);
	}
};
