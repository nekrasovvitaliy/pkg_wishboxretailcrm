<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RetailCrm\Api\Client;
use RetailCrm\Api\Model\Entity\Pagination;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\Product;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Request\Store\OffersRequest;
use RetailCrm\Api\Model\Response\Store\OffersResponse;
use RetailCrm\Api\ResourceGroup\Store;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;
use WishboxRetailCrmLibrary\Service\RetailCrmProductService;

/**
 * Tests normalized RetailCRM product loading.
 *
 * @since 1.0.0
 */
final class RetailCrmProductServiceTest extends TestCase
{
	/**
	 * Verify that offers are converted to transport-neutral product arrays.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testReturnsNormalizedProducts(): void
	{
		$response = new OffersResponse();
		$response->offers = [$this->createOffer()];
		$response->pagination = new Pagination();
		$response->pagination->totalPageCount = 1;
		$store = $this->createMock(Store::class);
		$store->expects(self::once())
			->method('offers')
			->with(
				self::callback(
					static fn (OffersRequest $request): bool => $request->limit === 100
						&& $request->page === 1
						&& $request->filter->sites === ['shop']
				)
			)
			->willReturn($response);
		$client = $this->createStub(Client::class);
		$client->store = $store;
		$service = new RetailCrmProductService(
			$client,
			new RetailCrmConfiguration('', '', '', 'shop')
		);

		$products = $service->getProducts(100, 'ozon_product_id', 'base');

		self::assertSame(
			[
				[
					'offer_id'   => 'CRM-101',
					'product_id' => 101,
					'price'      => 1490.0,
					'stock'      => 3,
				],
			],
			$products
		);
	}

	/**
	 * Create one RetailCRM offer fixture.
	 *
	 * @return StoreOffer RetailCRM offer fixture.
	 *
	 * @since 1.0.0
	 */
	private function createOffer(): StoreOffer
	{
		$price = new OfferPrice();
		$price->priceType = 'base';
		$price->price = 1490.0;
		$product = new Product();
		$product->active = true;
		$offer = new StoreOffer();
		$offer->externalId = 'CRM-101';
		$offer->properties = ['ozon_product_id' => 101];
		$offer->prices = [$price];
		$offer->quantity = 3.9;
		$offer->active = true;
		$offer->product = $product;

		return $offer;
	}
}
