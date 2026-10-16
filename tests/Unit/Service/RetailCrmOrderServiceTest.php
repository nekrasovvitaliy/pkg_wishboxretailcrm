<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Pagination;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
use RetailCrm\Api\Model\Response\Orders\OrdersCreateResponse;
use RetailCrm\Api\Model\Response\Orders\OrdersGetResponse;
use RetailCrm\Api\Model\Response\Orders\OrdersResponse;
use RetailCrm\Api\Model\Response\Store\OffersResponse;
use RetailCrm\Api\ResourceGroup\Orders;
use RetailCrm\Api\ResourceGroup\Store;
use RuntimeException;
use Wishbox\Dto\OrderImport\MarketplaceOrder;
use Wishbox\Dto\OrderImport\MarketplaceOrderDelivery;
use Wishbox\Dto\OrderImport\MarketplaceOrderItem;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;
use WishboxRetailCrmLibrary\Service\RetailCrmOrderService;

/**
 * Tests normalized marketplace order imports.
 *
 * @since 1.0.0
 */
final class RetailCrmOrderServiceTest extends TestCase
{
	/**
	 * Verify that an order is loaded by its internal RetailCRM identifier.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testGetsOrderByInternalId(): void
	{
		$order = new Order();
		$order->id = 77;
		$response = new OrdersGetResponse();
		$response->order = $order;
		$ordersApi = $this->createMock(Orders::class);
		$ordersApi->expects(self::once())
			->method('get')
			->with(
				77,
				self::callback(
					static fn (BySiteRequest $request): bool => $request->by === ByIdentifier::ID
						&& $request->site === 'shop'
				)
			)
			->willReturn($response);
		$service = $this->createService($ordersApi, $this->createStub(Store::class));

		self::assertSame($order, $service->getOrder(77));
	}

	/**
	 * Verify that a normalized order is converted and created in RetailCRM.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testImportsNormalizedOrder(): void
	{
		$ordersResponse = new OrdersResponse();
		$ordersResponse->orders = [];
		$createdResponse = new OrdersCreateResponse();
		$createdResponse->id = 501;
		$createdRequest = null;
		$ordersApi = $this->createMock(Orders::class);
		$ordersApi->expects(self::once())
			->method('list')
			->with(
				self::callback(
					static fn (OrdersRequest $request): bool => $request->filter->externalIds === ['VK-77']
				)
			)
			->willReturn($ordersResponse);
		$ordersApi->expects(self::once())
			->method('create')
			->willReturnCallback(
				static function (OrdersCreateRequest $request) use (&$createdRequest, $createdResponse): OrdersCreateResponse {
					$createdRequest = $request;

					return $createdResponse;
				}
			);
		$storeApi = $this->createStub(Store::class);
		$storeApi->method('offers')->willReturn($this->createOffersResponse());
		$service = $this->createService($ordersApi, $storeApi);

		$result = $service->importOrders(
			[$this->createOrderData()],
			$this->createOptions()
		);

		self::assertSame(1, $result->created);
		self::assertSame(['VK-77' => 501], $result->orderIds);
		self::assertInstanceOf(OrdersCreateRequest::class, $createdRequest);
		self::assertSame('shop', $createdRequest->site);
		self::assertSame('VK-77', $createdRequest->order->externalId);
		self::assertSame(9001, $createdRequest->order->items[0]->offer->id);
		self::assertSame(2.0, $createdRequest->order->items[0]->quantity);
		self::assertSame(1490.0, $createdRequest->order->items[0]->initialPrice);
		self::assertSame(5.0, $createdRequest->order->delivery->cost);
		self::assertSame('77', $createdRequest->order->customFields['vk_order_id']);
	}

	/**
	 * Verify that an existing order is skipped before offers are loaded.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testSkipsExistingOrder(): void
	{
		$ordersResponse = new OrdersResponse();
		$ordersResponse->orders = [new Order()];
		$ordersApi = $this->createMock(Orders::class);
		$ordersApi->expects(self::once())->method('list')->willReturn($ordersResponse);
		$ordersApi->expects(self::never())->method('create');
		$storeApi = $this->createMock(Store::class);
		$storeApi->expects(self::never())->method('offers');
		$service = $this->createService($ordersApi, $storeApi);

		$result = $service->importOrders([$this->createOrderData()], $this->createOptions());

		self::assertSame(0, $result->created);
		self::assertSame(1, $result->skipped);
		self::assertSame(0, $result->failed);
	}

	/**
	 * Verify that RetailCRM creation failures are reported per source order.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testReportsCreationFailure(): void
	{
		$ordersResponse = new OrdersResponse();
		$ordersResponse->orders = [];
		$ordersApi = $this->createStub(Orders::class);
		$ordersApi->method('list')->willReturn($ordersResponse);
		$ordersApi->method('create')->willThrowException(new RuntimeException('RetailCRM request failed.'));
		$storeApi = $this->createStub(Store::class);
		$storeApi->method('offers')->willReturn($this->createOffersResponse());
		$service = $this->createService($ordersApi, $storeApi);

		$result = $service->importOrders([$this->createOrderData()], $this->createOptions());

		self::assertSame(0, $result->created);
		self::assertSame(1, $result->failed);
		self::assertSame(['VK-77' => 'RetailCRM request failed.'], $result->errors);
	}

	/**
	 * Create the service under test.
	 *
	 * @param   Orders  $ordersApi  RetailCRM orders resource.
	 * @param   Store   $storeApi   RetailCRM store resource.
	 *
	 * @return RetailCrmOrderService Configured service.
	 *
	 * @since 1.0.0
	 */
	private function createService(Orders $ordersApi, Store $storeApi): RetailCrmOrderService
	{
		$client = $this->createStub(Client::class);
		$client->orders = $ordersApi;
		$client->store = $storeApi;

		return new RetailCrmOrderService(
			$client,
			new RetailCrmConfiguration('', '', '', 'shop')
		);
	}

	/**
	 * Create a normalized order fixture.
	 *
	 * @return MarketplaceOrder Normalized order.
	 *
	 * @since 1.0.0
	 */
	private function createOrderData(): MarketplaceOrder
	{
		return new MarketplaceOrder(
			sourceId: 77,
			externalId: 'VK-77',
			items: [
				new MarketplaceOrderItem(
					productId: 321,
					quantity: 2,
					price: 1490.0,
					name: 'Test product'
				),
			],
			firstName: 'Ivan',
			lastName: 'Ivanov',
			phone: '+79990000000',
			customerComment: 'Call before delivery',
			managerComment: 'Imported from VK.',
			delivery: new MarketplaceOrderDelivery(
				cost: 5.0,
				city: 'Moscow',
				street: 'Tverskaya',
				building: '1'
			)
		);
	}

	/**
	 * Create RetailCRM import options.
	 *
	 * @return array<string, mixed> Import options.
	 *
	 * @since 1.0.0
	 */
	private function createOptions(): array
	{
		return [
			'product_id_property' => 'vk_market_item_id',
			'offer_lookup_limit'  => 100,
			'price_type'          => 'base',
			'currency'            => 'RUB',
			'order_type'          => 'eshop-individual',
			'order_method'        => 'shopping-cart',
			'order_status'        => 'new',
			'marketplace_code'    => 'vk',
			'source_order_field'  => 'vk_order_id',
		];
	}

	/**
	 * Create a RetailCRM offers response fixture.
	 *
	 * @return OffersResponse Offers response.
	 *
	 * @since 1.0.0
	 */
	private function createOffersResponse(): OffersResponse
	{
		$price = new OfferPrice();
		$price->priceType = 'base';
		$price->price = 1490.0;
		$offer = new StoreOffer();
		$offer->id = 9001;
		$offer->externalId = 'SKU-1';
		$offer->name = 'Test product';
		$offer->properties = ['vk_market_item_id' => 321];
		$offer->prices = [$price];
		$response = new OffersResponse();
		$response->offers = [$offer];
		$response->pagination = new Pagination();
		$response->pagination->totalPageCount = 1;

		return $response;
	}
}
