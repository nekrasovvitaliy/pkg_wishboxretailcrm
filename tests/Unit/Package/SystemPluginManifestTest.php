<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Unit\Package;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Verifies the system plugin package metadata and translations.
 *
 * @since 1.0.0
 */
final class SystemPluginManifestTest extends TestCase
{
	private const array CONFIGURATION_FIELDS = [
		'retailcrm_api_url',
		'retailcrm_api_key',
		'retailcrm_proxy_token',
		'retailcrm_site',
	];

	private const array CONFIGURATION_LANGUAGE_KEYS = [
		'PLG_SYSTEM_WISHBOXRETAILCRM_API_URL_LABEL',
		'PLG_SYSTEM_WISHBOXRETAILCRM_API_URL_DESCRIPTION',
		'PLG_SYSTEM_WISHBOXRETAILCRM_API_KEY_LABEL',
		'PLG_SYSTEM_WISHBOXRETAILCRM_API_KEY_DESCRIPTION',
		'PLG_SYSTEM_WISHBOXRETAILCRM_PROXY_TOKEN_LABEL',
		'PLG_SYSTEM_WISHBOXRETAILCRM_PROXY_TOKEN_DESCRIPTION',
		'PLG_SYSTEM_WISHBOXRETAILCRM_SITE_LABEL',
		'PLG_SYSTEM_WISHBOXRETAILCRM_SITE_DESCRIPTION',
	];

	/**
	 * Ensure package and plugin manifests use the same plugin group.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testPluginGroupIsSystemEverywhere(): void
	{
		$projectRoot = dirname(__DIR__, 3);
		$pluginManifest = simplexml_load_file(
			$projectRoot . '/plg_system_wishboxretailcrm/wishboxretailcrm.xml'
		);

		self::assertInstanceOf(SimpleXMLElement::class, $pluginManifest);
		self::assertSame('plugin', (string) $pluginManifest['type']);
		self::assertSame('system', (string) $pluginManifest['group']);

		$packageManifest = simplexml_load_file($projectRoot . '/pkg_wishboxretailcrm.xml');

		self::assertInstanceOf(SimpleXMLElement::class, $packageManifest);

		$pluginFile = $packageManifest->xpath(
			'files/file[@type="plugin" and @id="wishboxretailcrm"]'
		);

		self::assertIsArray($pluginFile);
		self::assertCount(1, $pluginFile);
		self::assertSame('system', (string) $pluginFile[0]['group']);
	}

	/**
	 * Ensure the shared RetailCRM library is included in the package.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testPackageContainsSharedLibrary(): void
	{
		$projectRoot = dirname(__DIR__, 3);
		$packageManifest = simplexml_load_file($projectRoot . '/pkg_wishboxretailcrm.xml');

		self::assertInstanceOf(SimpleXMLElement::class, $packageManifest);

		$libraryFile = $packageManifest->xpath(
			'files/file[@type="library" and @id="wishboxretailcrm"]'
		);

		self::assertIsArray($libraryFile);
		self::assertCount(1, $libraryFile);
		self::assertSame('lib_wishboxretailcrm.zip', (string) $libraryFile[0]);
	}

	/**
	 * Ensure configuration field labels and descriptions are translated.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testConfigurationLanguageKeysExist(): void
	{
		$pluginRoot = dirname(__DIR__, 3) . '/plg_system_wishboxretailcrm';

		foreach (['en-GB', 'ru-RU'] as $locale)
		{
			$translations = parse_ini_file(
				$pluginRoot . '/language/' . $locale . '/plg_system_wishboxretailcrm.ini'
			);

			self::assertIsArray($translations);

			foreach (self::CONFIGURATION_LANGUAGE_KEYS as $languageKey)
			{
				self::assertArrayHasKey($languageKey, $translations);
				self::assertNotSame('', $translations[$languageKey]);
			}
		}
	}

	/**
	 * Ensure the plugin exposes only the shared RetailCRM settings.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testPluginContainsOnlyExpectedSettings(): void
	{
		$projectRoot = dirname(__DIR__, 3);
		$pluginManifest = simplexml_load_file(
			$projectRoot . '/plg_system_wishboxretailcrm/wishboxretailcrm.xml'
		);

		self::assertInstanceOf(SimpleXMLElement::class, $pluginManifest);

		$fields = $pluginManifest->xpath('config/fields/fieldset/field');

		self::assertIsArray($fields);

		$fieldNames = array_map(
			static fn (SimpleXMLElement $field): string => (string) $field['name'],
			$fields
		);

		self::assertSame(self::CONFIGURATION_FIELDS, $fieldNames);
	}
}
