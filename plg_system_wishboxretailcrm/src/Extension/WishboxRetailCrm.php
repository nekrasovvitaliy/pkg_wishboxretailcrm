<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\System\WishboxRetailCrm\Extension;

use Joomla\CMS\Plugin\CMSPlugin;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Stores the RetailCRM configuration used by Wishbox integrations.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final class WishboxRetailCrm extends CMSPlugin
{
	/**
	 * Load the plugin language automatically.
	 *
	 * @var boolean
	 *
	 * @since 1.0.0
	 */
	protected $autoloadLanguage = true;
}
