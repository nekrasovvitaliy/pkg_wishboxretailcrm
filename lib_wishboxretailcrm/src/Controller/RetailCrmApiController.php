<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Controller;

use Joomla\CMS\Application\ApiApplication;
use Joomla\CMS\MVC\Controller\ApiController;
use WishboxRetailCrmLibrary\Document\RetailCrmJsonDocument;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides RetailCRM response handling for Joomla API controllers.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
abstract class RetailCrmApiController extends ApiController
{
	/**
	 * Configure a JSON response compatible with RetailCRM.
	 *
	 * @param   array<string, mixed>  $body        Response body.
	 * @param   int                   $statusCode  HTTP status code.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	protected function setRetailCrmResponse(array $body, int $statusCode): void
	{
		/** @var ApiApplication $app */
		$app = $this->app;

		$retailCrmJsonDocument = new RetailCrmJsonDocument();
		$retailCrmJsonDocument->setPayload($body);

		$app->setHeader('status', (string) $statusCode, true);
		$app->loadDocument($retailCrmJsonDocument);
	}
}
