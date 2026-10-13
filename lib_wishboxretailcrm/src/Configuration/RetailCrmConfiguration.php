<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Configuration;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Contains the shared RetailCRM connection settings.
 *
 * @since 1.0.0
 */
final readonly class RetailCrmConfiguration
{
	/**
	 * Initialise the RetailCRM connection settings.
	 *
	 * @param   string  $apiUrl      RetailCRM API or proxy URL.
	 * @param   string  $apiKey      RetailCRM API key.
	 * @param   string  $proxyToken  Optional proxy authentication token.
	 * @param   string  $site        RetailCRM site code.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public string $apiUrl,
		public string $apiKey,
		public string $proxyToken,
		public string $site
	) {
	}
}
