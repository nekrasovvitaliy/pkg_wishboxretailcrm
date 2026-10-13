<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Factory;

use RetailCrm\Api\Builder\ClientBuilder;
use RetailCrm\Api\Builder\FormEncoderBuilder;
use RetailCrm\Api\Client;
use WishboxRetailCrmLibrary\Handler\RetailCrmProxyAuthenticatorHandler;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates a RetailCRM SDK client with optional proxy authentication.
 *
 * @since 1.0.0
 */
final class RetailCrmClientFactory
{
	/**
	 * Create a configured RetailCRM SDK client.
	 *
	 * @param   string  $apiUrl      RetailCRM API or proxy URL.
	 * @param   string  $apiKey      RetailCRM API key.
	 * @param   string  $proxyToken  Optional proxy authentication token.
	 *
	 * @return Client
	 *
	 * @since 1.0.0
	 */
	public static function createClient(string $apiUrl, string $apiKey, string $proxyToken = ''): Client
	{
		return (new ClientBuilder())
			->setApiUrl($apiUrl)
			->setAuthenticatorHandler(new RetailCrmProxyAuthenticatorHandler($apiKey, $proxyToken))
			->setFormEncoder((new FormEncoderBuilder())->build())
			->build();
	}
}
