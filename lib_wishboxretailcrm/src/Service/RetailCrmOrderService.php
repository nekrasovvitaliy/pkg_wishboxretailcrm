<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxRetailCrmLibrary\Service;

use InvalidArgumentException;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Exception\Api\AccountDoesNotExistException;
use RetailCrm\Api\Exception\Api\ApiErrorException;
use RetailCrm\Api\Exception\Api\MissingCredentialsException;
use RetailCrm\Api\Exception\Api\MissingParameterException;
use RetailCrm\Api\Exception\Api\ValidationException;
use RetailCrm\Api\Exception\Client\HandlerException;
use RetailCrm\Api\Exception\Client\HttpClientException;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Delivery\OrderDeliveryAddress;
use RetailCrm\Api\Model\Entity\Orders\Delivery\SerializedOrderDelivery;
use RetailCrm\Api\Model\Entity\Orders\Items\Offer;
use RetailCrm\Api\Model\Entity\Orders\Items\OrderProduct;
use RetailCrm\Api\Model\Entity\Orders\MarketplaceData;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Filter\Orders\OrderFilter;
use RetailCrm\Api\Model\Filter\Store\OfferFilterType;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
use RetailCrm\Api\Model\Request\Store\OffersRequest;
use RuntimeException;
use Throwable;
use Wishbox\Dto\OrderImport\MarketplaceOrder;
use Wishbox\Dto\OrderImport\MarketplaceOrderDelivery;
use Wishbox\Dto\OrderImport\MarketplaceOrderItem;
use Wishbox\Dto\OrderImport\OrderImportResult;
use WishboxRetailCrmLibrary\Configuration\RetailCrmConfiguration;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Imports transport-neutral marketplace orders into RetailCRM.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final readonly class RetailCrmOrderService
{
	private const int PAGE_LIMIT = 100;

	/**
	 * Initialize the RetailCRM order service.
	 *
	 * @param   Client                  $retailCrmClient         RetailCRM API client.
	 * @param   RetailCrmConfiguration  $retailCrmConfiguration RetailCRM connection settings.
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
	 * Get a RetailCRM order by its internal identifier.
	 *
	 * @param   int  $orderId  RetailCRM order identifier.
	 *
	 * @return Order RetailCRM order.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws InvalidArgumentException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws RuntimeException
	 * @throws ValidationException
	 *
	 * @since 1.0.0
	 */
	public function getOrder(int $orderId): Order
	{
		if ($orderId < 1)
		{
			throw new InvalidArgumentException('RetailCRM order ID must be more than zero.');
		}

		$site = trim($this->retailCrmConfiguration->site);

		if ($site === '')
		{
			throw new RuntimeException('RetailCRM site code must be configured.');
		}

		$response = $this->retailCrmClient->orders->get(
			$orderId,
			new BySiteRequest(ByIdentifier::ID, $site)
		);
		$order = $response->order ?? null;

		if (!$order instanceof Order)
		{
			throw new RuntimeException('RetailCRM did not return the requested order.');
		}

		return $order;
	}

	/**
	 * Import normalized marketplace orders.
	 *
	 * @param   list<MarketplaceOrder>  $orders   Normalized marketplace orders.
	 * @param   array<string, mixed>    $options  RetailCRM order mapping options.
	 *
	 * @return OrderImportResult Import result.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function importOrders(array $orders, array $options = []): OrderImportResult
	{
		$site = trim($this->retailCrmConfiguration->site);

		if ($site === '')
		{
			throw new RuntimeException('RetailCRM site code must be configured.');
		}

		$created = 0;
		$skipped = 0;
		$failed = 0;
		$orderIds = [];
		$errors = [];

		foreach ($orders as $marketplaceOrder)
		{
			if (!$marketplaceOrder instanceof MarketplaceOrder)
			{
				$failed++;
				$errors['unknown'] = 'The order must be an instance of MarketplaceOrder.';

				continue;
			}

			$externalId = $marketplaceOrder->externalId !== '' ? $marketplaceOrder->externalId : 'unknown';

			try
			{
				$this->validateOrder($marketplaceOrder);

				if ($this->orderExists($externalId))
				{
					$skipped++;

					continue;
				}

				$retailCrmOrder = $this->buildOrder($marketplaceOrder, $options, $site);
				$retailCrmOrderId = $this->createOrder($retailCrmOrder, $site);
				$created++;
				$orderIds[$externalId] = $retailCrmOrderId;
			}
			catch (Throwable $throwable)
			{
				$failed++;
				$errors[$externalId] = $throwable->getMessage();
			}
		}

		return new OrderImportResult($created, $skipped, $failed, $orderIds, $errors);
	}

	/**
	 * Validate one normalized order.
	 *
	 * @param   MarketplaceOrder  $marketplaceOrder  Normalized order.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function validateOrder(MarketplaceOrder $marketplaceOrder): void
	{
		$error = trim($marketplaceOrder->error);

		if ($error !== '')
		{
			throw new RuntimeException($error);
		}

		if (trim((string) $marketplaceOrder->sourceId) === '')
		{
			throw new RuntimeException('Marketplace order ID must be configured.');
		}

		if (trim($marketplaceOrder->externalId) === '')
		{
			throw new RuntimeException('Marketplace order external ID must be configured.');
		}
	}

	/**
	 * Check whether an order already exists.
	 *
	 * @param   string  $externalId  External order identifier.
	 *
	 * @return bool Whether the order exists.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @since 1.0.0
	 */
	private function orderExists(string $externalId): bool
	{
		$request = new OrdersRequest();
		$request->limit = 20;
		$request->page = 1;
		$request->filter = new OrderFilter();
		$request->filter->externalIds = [$externalId];
		$response = $this->retailCrmClient->orders->list($request);

		return is_array($response->orders ?? null) && $response->orders !== [];
	}

	/**
	 * Build a RetailCRM order entity.
	 *
	 * @param   MarketplaceOrder      $marketplaceOrder  Normalized order.
	 * @param   array<string, mixed>  $options           RetailCRM order mapping options.
	 * @param   string                $site              RetailCRM site code.
	 *
	 * @return Order RetailCRM order entity.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @since 1.0.0
	 */
	private function buildOrder(MarketplaceOrder $marketplaceOrder, array $options, string $site): Order
	{
		$productIds = array_map(
			static fn (MarketplaceOrderItem $item): int => (int) $item->productId,
			$marketplaceOrder->items
		);
		$productIdProperty = trim((string) ($options['product_id_property'] ?? ''));
		$offerLookupLimit = max(1, (int) ($options['offer_lookup_limit'] ?? 10000));
		$offers = $this->getOffersByProductIds($productIds, $productIdProperty, $offerLookupLimit);
		$orderItems = $this->buildOrderItems(
			$marketplaceOrder->items,
			$offers,
			(string) ($options['price_type'] ?? 'base')
		);

		if ($orderItems === [])
		{
			throw new RuntimeException('Marketplace order does not contain any products.');
		}

		$externalId = trim($marketplaceOrder->externalId);
		$order = new Order();
		$order->externalId = $externalId;
		$order->site = $site;
		$order->currency = (string) ($options['currency'] ?? 'RUB');
		$order->orderType = (string) ($options['order_type'] ?? 'eshop-individual');
		$order->orderMethod = (string) ($options['order_method'] ?? 'shopping-cart');
		$order->status = (string) ($options['order_status'] ?? 'new');
		$order->firstName = $marketplaceOrder->firstName;
		$order->lastName = $marketplaceOrder->lastName;
		$order->patronymic = $marketplaceOrder->patronymic;
		$order->phone = $marketplaceOrder->phone;
		$order->email = $marketplaceOrder->email;
		$order->customerComment = $marketplaceOrder->customerComment;
		$order->managerComment = $marketplaceOrder->managerComment;
		$order->items = $orderItems;
		$order->delivery = $this->buildDelivery($marketplaceOrder->delivery);

		$this->setMarketplaceData($order, $externalId, $options);
		$this->setSourceOrderField($order, $marketplaceOrder->sourceId, $options);

		return $order;
	}

	/**
	 * Build RetailCRM order products.
	 *
	 * @param   list<MarketplaceOrderItem>  $items      Normalized items.
	 * @param   array<int, StoreOffer>       $offers     Offers indexed by marketplace product ID.
	 * @param   string                       $priceType  RetailCRM price type code.
	 *
	 * @return list<OrderProduct> RetailCRM order products.
	 *
	 * @since 1.0.0
	 */
	private function buildOrderItems(array $items, array $offers, string $priceType): array
	{
		$orderItems = [];

		foreach ($items as $itemData)
		{
			$productId = (int) $itemData->productId;

			if ($productId < 1 || !isset($offers[$productId]))
			{
				throw new RuntimeException('RetailCRM offer was not found for marketplace product ' . $productId . '.');
			}

			$retailCrmOffer = $offers[$productId];
			$offer = new Offer();
			$offer->id = $retailCrmOffer->id;
			$offer->externalId = $retailCrmOffer->externalId ?? '';
			$orderItem = new OrderProduct();
			$orderItem->offer = $offer;
			$orderItem->quantity = max(1.0, $itemData->quantity);
			$orderItem->initialPrice = $itemData->price !== null
				? $itemData->price
				: ($this->getOfferPrice($retailCrmOffer, $priceType) ?? 0.0);
			$orderItem->productName = $itemData->name !== ''
				? $itemData->name
				: $retailCrmOffer->name ?? ('Marketplace product #' . $productId);
			$orderItems[] = $orderItem;
		}

		return $orderItems;
	}

	/**
	 * Resolve RetailCRM offers by marketplace product identifier.
	 *
	 * @param   list<int>  $productIds    Marketplace product identifiers.
	 * @param   string     $propertyCode  RetailCRM property code.
	 * @param   int        $maxOffers     Maximum offers to inspect.
	 *
	 * @return array<int, StoreOffer> Offers indexed by marketplace product ID.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @since 1.0.0
	 */
	private function getOffersByProductIds(array $productIds, string $propertyCode, int $maxOffers): array
	{
		$wantedIds = array_map('intval', $productIds)
				|> (fn($x) => array_filter($x, static fn(int $productId): bool => $productId > 0))
				|> array_unique(...)
				|> array_values(...);

		if ($wantedIds === [] || $propertyCode === '')
		{
			return [];
		}

		$wantedIdMap = array_fill_keys($wantedIds, true);
		$result = [];

		foreach ($this->getOffers($maxOffers) as $offer)
		{
			$productId = $this->getOfferProductId($offer, $propertyCode);

			if (isset($wantedIdMap[$productId]))
			{
				$result[$productId] = $offer;
			}

			if (count($result) === count($wantedIdMap))
			{
				break;
			}
		}

		return $result;
	}

	/**
	 * Load RetailCRM offers page by page.
	 *
	 * @param   int  $maxOffers  Maximum offers to load.
	 *
	 * @return list<StoreOffer> RetailCRM offers.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @since 1.0.0
	 */
	private function getOffers(int $maxOffers): array
	{
		$page = 1;
		$offers = [];

		do
		{
			$request = new OffersRequest();
			$request->limit = min(self::PAGE_LIMIT, $maxOffers - count($offers));
			$request->page = $page;
			$request->filter = new OfferFilterType();
			$request->filter->sites = [$this->retailCrmConfiguration->site];
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

			$totalPages = $response->pagination->totalPageCount ?? $page;
			$page++;
		}
		while ($page <= $totalPages && $pageOffers !== []);

		return $offers;
	}

	/**
	 * Read a marketplace product identifier from a RetailCRM offer.
	 *
	 * @param   StoreOffer  $offer         RetailCRM offer.
	 * @param   string      $propertyCode  Property code.
	 *
	 * @return int Marketplace product identifier.
	 *
	 * @since 1.0.0
	 */
	private function getOfferProductId(StoreOffer $offer, string $propertyCode): int
	{
		$value = $this->getPropertyValue($offer->properties ?? [], $propertyCode);

		if ($value === null && isset($offer->product))
		{
			$value = $this->getPropertyValue($offer->product->properties ?? [], $propertyCode);
		}

		return is_numeric($value) ? max(0, (int) $value) : 0;
	}

	/**
	 * Read a property value from supported RetailCRM representations.
	 *
	 * @param   mixed   $properties    RetailCRM properties.
	 * @param   string  $propertyCode  Property code.
	 *
	 * @return mixed Property value.
	 *
	 * @since 1.0.0
	 */
	private function getPropertyValue(mixed $properties, string $propertyCode): mixed
	{
		if (!is_array($properties))
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

	/**
	 * Read a configured RetailCRM offer price.
	 *
	 * @param   StoreOffer  $offer      RetailCRM offer.
	 * @param   string      $priceType  Price type code.
	 *
	 * @return float|null Offer price.
	 *
	 * @since 1.0.0
	 */
	private function getOfferPrice(StoreOffer $offer, string $priceType): ?float
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
	 * Build RetailCRM delivery data.
	 *
	 * @param   MarketplaceOrderDelivery|null  $deliveryData  Normalized delivery data.
	 *
	 * @return SerializedOrderDelivery RetailCRM delivery entity.
	 *
	 * @since 1.0.0
	 */
	private function buildDelivery(?MarketplaceOrderDelivery $deliveryData): SerializedOrderDelivery
	{
		$deliveryData ??= new MarketplaceOrderDelivery();
		$address = new OrderDeliveryAddress();
		$address->city = $deliveryData->city;
		$address->street = $deliveryData->street;
		$address->building = $deliveryData->building;
		$address->flat = $deliveryData->flat;
		$address->text = $deliveryData->text;
		$address->notes = $deliveryData->notes;
		$delivery = new SerializedOrderDelivery();
		$delivery->address = $address;
		$delivery->cost = max(0.0, $deliveryData->cost);

		return $delivery;
	}

	/**
	 * Set optional marketplace metadata.
	 *
	 * @param   Order                 $order       RetailCRM order.
	 * @param   string                $externalId  External order identifier.
	 * @param   array<string, mixed>  $options     RetailCRM order mapping options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function setMarketplaceData(Order $order, string $externalId, array $options): void
	{
		$marketplaceCode = trim((string) ($options['marketplace_code'] ?? ''));

		if ($marketplaceCode === '')
		{
			return;
		}

		$order->marketplace = new MarketplaceData();
		$order->marketplace->code = $marketplaceCode;
		$order->marketplace->orderId = $externalId;
	}

	/**
	 * Set an optional source order custom field.
	 *
	 * @param   Order                 $order     RetailCRM order.
	 * @param   int|string            $sourceId  Marketplace order identifier.
	 * @param   array<string, mixed>  $options   RetailCRM order mapping options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function setSourceOrderField(Order $order, int|string $sourceId, array $options): void
	{
		$sourceOrderField = trim((string) ($options['source_order_field'] ?? ''));

		if ($sourceOrderField !== '')
		{
			$order->customFields = [$sourceOrderField => (string) $sourceId];
		}
	}

	/**
	 * Create one RetailCRM order.
	 *
	 * @param   Order   $order  RetailCRM order.
	 * @param   string  $site   RetailCRM site code.
	 *
	 * @return int Created RetailCRM order identifier.
	 *
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @since 1.0.0
	 */
	private function createOrder(Order $order, string $site): int
	{
		$request = new OrdersCreateRequest();
		$request->order = $order;
		$request->site = $site;
		$response = $this->retailCrmClient->orders->create($request);
		$orderId = $response->id ?? 0;

		if ($orderId < 1)
		{
			throw new RuntimeException('RetailCRM did not return the created order ID.');
		}

		return $orderId;
	}
}
