<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\Integration\Module;

use Dice\Dice;
use Friendica\App;
use Friendica\AppHelper;
use Friendica\Core\L10n;
use Friendica\DI;
use Friendica\Event\EventDispatcher;
use Friendica\Factory\Api\Mastodon\Error;
use Friendica\Factory\Api\Twitter\User as TwitterUser;
use Friendica\Module\Api\ApiResponse;
use Friendica\Module\BaseApi;
use Friendica\Util\Profiler;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class BaseApiTest extends TestCase
{
	public function testHandleRequestGetReturnsResponse(): void
	{
		$eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$eventDispatcher->method('dispatch')->willReturnArgument(0);

		$dice = new Dice();
		$dice = $dice->addRule(EventDispatcherInterface::class, [
			'instanceOf' => EventDispatcher::class,
			'shared'     => true,
		]);
		DI::init($dice, true);

		$args = $this->createMock(App\Arguments::class);
		$args->method('getQueryString')->willReturn('api/test');
		$args->method('getModuleName')->willReturn('Test');
		$args->method('getMethod')->willReturn('GET');

		$apiResponse = new ApiResponse(
			$this->createMock(L10n::class),
			$args,
			$this->createMock(LoggerInterface::class),
			$this->createMock(App\BaseURL::class),
			$this->createMock(TwitterUser::class),
		);

		$module = $this->getMockBuilder(BaseApi::class)
			->setConstructorArgs([
				$this->createMock(Error::class),
				$this->createMock(AppHelper::class),
				$this->createMock(L10n::class),
				$this->createMock(App\BaseURL::class),
				$args,
				$this->createMock(LoggerInterface::class),
				$this->createMock(Profiler::class),
				$apiResponse,
				[],
				[],
			])
			->onlyMethods(['content'])
			->getMock();

		$module->method('content')->willReturn('{"status":"ok"}');

		$request = new ServerRequest('GET', 'https://friendica.local/api/test');
		$result  = $module->handleRequest($request);

		$this->assertEquals(200, $result->getStatusCode());
		$this->assertJson((string) $result->getBody());
	}
}