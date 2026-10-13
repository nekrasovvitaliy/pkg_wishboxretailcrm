<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

declare(strict_types=1);

namespace Tests\Unit\Handler;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RetailCrm\Api\Model\RequestData;
use WishboxRetailCrmLibrary\Handler\RetailCrmProxyAuthenticatorHandler;

/**
 * Verifies the shared RetailCRM authentication headers.
 *
 * @since 1.0.0
 */
final class RetailCrmProxyAuthenticatorHandlerTest extends TestCase
{
	/**
	 * Ensure both API and proxy credentials are added.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testAddsApiKeyAndProxyTokenHeaders(): void
	{
		$request = $this->createMock(RequestInterface::class);
		$requestWithApiKey = $this->createMock(RequestInterface::class);
		$requestWithProxyToken = $this->createStub(RequestInterface::class);
		$requestData = new RequestData('GET', '/orders', null);
		$requestData->request = $request;

		$request->expects(self::once())
			->method('withAddedHeader')
			->with('X-Api-Key', 'api-key')
			->willReturn($requestWithApiKey);

		$requestWithApiKey->expects(self::once())
			->method('withAddedHeader')
			->with('X-RetailCRM-Proxy-Token', 'proxy-token')
			->willReturn($requestWithProxyToken);

		$handler = new RetailCrmProxyAuthenticatorHandler('api-key', 'proxy-token');
		$handler->handle($requestData);

		self::assertSame($requestWithProxyToken, $requestData->request);
	}

	/**
	 * Ensure an empty proxy token does not add a proxy header.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function testOmitsEmptyProxyToken(): void
	{
		$request = $this->createMock(RequestInterface::class);
		$requestWithApiKey = $this->createMock(RequestInterface::class);
		$requestData = new RequestData('GET', '/orders', null);
		$requestData->request = $request;

		$request->expects(self::once())
			->method('withAddedHeader')
			->with('X-Api-Key', 'api-key')
			->willReturn($requestWithApiKey);

		$requestWithApiKey->expects(self::never())->method('withAddedHeader');

		$handler = new RetailCrmProxyAuthenticatorHandler('api-key');
		$handler->handle($requestData);

		self::assertSame($requestWithApiKey, $requestData->request);
	}
}
