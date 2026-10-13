<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Service;

use RetailCrm\Api\Client;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Filter\Store\OfferFilterType;
use RetailCrm\Api\Model\Request\Store\OffersRequest;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reads normalized product data from RetailCRM.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final readonly class RetailCrmProductService
{
	private const int PAGE_LIMIT = 100;

	/**
	 * Initialise the RetailCRM product service.
	 *
	 * @param   Client                  $retailCrmClient        RetailCRM API client.
	 * @param   RetailCrmConfiguration  $retailCrmConfiguration  RetailCRM connection settings.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private Client $retailCrmClient,
		private RetailCrmConfiguration $retailCrmConfiguration
	) {
	}

	/**
	 * Return normalized RetailCRM products for an external marketplace.
	 *
	 * @param   int     $maxProducts       Maximum products to return.
	 * @param   string  $productIdProperty  External product ID property code.
	 * @param   string  $priceType          RetailCRM price type code.
	 *
	 * @return list<array{offer_id: string, product_id: int, price: float|null, stock: int}>
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function getProducts(
		int $maxProducts = 1000,
		string $productIdProperty = '',
		string $priceType = 'base'
	): array {
		$products = [];

		foreach ($this->getOffers($maxProducts) as $offer)
		{
			$products[] = [
				'offer_id'   => trim((string) ($offer->externalId ?? '')),
				'product_id' => $this->getProductId($offer, $productIdProperty),
				'price'      => $this->getPrice($offer, $priceType),
				'stock'      => $this->getStock($offer),
			];
		}

		return $products;
	}

	/**
	 * Load RetailCRM offers page by page.
	 *
	 * @param   int  $maxOffers  Maximum offers to load.
	 *
	 * @return list<StoreOffer>
	 *
	 * @since 1.0.0
	 */
	private function getOffers(int $maxOffers): array
	{
		$maxOffers = max(1, $maxOffers);
		$page = 1;
		$offers = [];

		do
		{
			$request = new OffersRequest();
			$request->limit = min(self::PAGE_LIMIT, $maxOffers - count($offers));
			$request->page = $page;

			if ($this->retailCrmConfiguration->site !== '')
			{
				$request->filter = new OfferFilterType();
				$request->filter->sites = [$this->retailCrmConfiguration->site];
			}

			$response = $this->retailCrmClient->store->offers($request);
			$pageOffers = is_array($response->offers ?? null) ? $response->offers : [];

			foreach ($pageOffers as $offer)
			{
				if ($offer instanceof StoreOffer)
				{
					$offers[] = $offer;
				}

				if (count($offers) >= $maxOffers)
				{
					break 2;
				}
			}

			$totalPages = (int) ($response->pagination->totalPageCount ?? $page);
			$page++;
		}
		while ($page <= $totalPages && $pageOffers !== []);

		return $offers;
	}

	/**
	 * Read an external product ID from offer or product properties.
	 *
	 * @param   StoreOffer  $offer         RetailCRM offer.
	 * @param   string      $propertyCode  Property code to read.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	private function getProductId(StoreOffer $offer, string $propertyCode): int
	{
		$value = $this->getPropertyValue($offer->properties ?? [], $propertyCode);

		if ($value === null && isset($offer->product))
		{
			$value = $this->getPropertyValue($offer->product->properties ?? [], $propertyCode);
		}

		return is_numeric($value) ? max(0, (int) $value) : 0;
	}

	/**
	 * Read a configured offer price.
	 *
	 * @param   StoreOffer  $offer      RetailCRM offer.
	 * @param   string      $priceType  Price type code.
	 *
	 * @return float|null
	 *
	 * @since 1.0.0
	 */
	private function getPrice(StoreOffer $offer, string $priceType): ?float
	{
		$fallbackPrice = null;

		foreach (is_array($offer->prices ?? null) ? $offer->prices : [] as $price)
		{
			if (!$price instanceof OfferPrice || !is_numeric($price->price))
			{
				continue;
			}

			$fallbackPrice ??= (float) $price->price;

			if ($price->priceType === $priceType)
			{
				return max(0.0, (float) $price->price);
			}
		}

		return $fallbackPrice !== null ? max(0.0, $fallbackPrice) : null;
	}

	/**
	 * Calculate the available offer quantity.
	 *
	 * @param   StoreOffer  $offer  RetailCRM offer.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	private function getStock(StoreOffer $offer): int
	{
		$offerActive = !isset($offer->active) || $offer->active;
		$productActive = !isset($offer->product->active) || $offer->product->active;

		if (!$offerActive || !$productActive)
		{
			return 0;
		}

		return max(0, (int) floor((float) ($offer->quantity ?? 0)));
	}

	/**
	 * Read a property from supported RetailCRM property representations.
	 *
	 * @param   mixed   $properties    Offer or product properties.
	 * @param   string  $propertyCode  Property code to read.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	private function getPropertyValue(mixed $properties, string $propertyCode): mixed
	{
		if ($propertyCode === '' || !is_array($properties))
		{
			return null;
		}

		if (array_key_exists($propertyCode, $properties))
		{
			return $properties[$propertyCode];
		}

		foreach ($properties as $property)
		{
			if (is_object($property) && (string) ($property->code ?? '') === $propertyCode)
			{
				return $property->value ?? null;
			}
		}

		return null;
	}
}
