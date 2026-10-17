<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Document;

use Joomla\CMS\Document\JsonDocument;
use JsonException;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Renders JSON responses expected by RetailCRM.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final class RetailCrmJsonDocument extends JsonDocument
{
	/**
	 * Response payload.
	 *
	 * @var array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	private array $payload = [];

	/**
	 * Set the response payload.
	 *
	 * @param   array<string, mixed>  $payload  Response payload.
	 *
	 * @return self
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function setPayload(array $payload): self
	{
		$this->payload = $payload;

		return $this;
	}

	/**
	 * Render the response payload.
	 *
	 * @param   bool                  $cache   Whether caching is enabled.
	 * @param   array<string, mixed>  $params  Rendering parameters.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 *
	 * @throws JsonException
	 */
	public function render($cache = false, $params = []): string
	{
		parent::render($cache, $params);

		return json_encode(
			$this->payload,
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
		);
	}
}
