<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Integration\Module\Api\Mastodon;

use Friendica\Core\EarlyExitException;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\Tag;
use Friendica\Module\Api\Mastodon\Search;
use Friendica\Test\ApiTestCase;
use Friendica\Test\Util\AuthTestConfig;
use GuzzleHttp\Psr7\ServerRequest;

final class SearchTest extends ApiTestCase
{
	public function testApiSearchReturnsAccounts(): void
	{
		$gserver = DBA::selectFirst('gserver', ['id'], ['nurl' => 'http://friendica.local']);
		DBA::update('gserver', ['failed' => 0, 'blocked' => 0], ['id' => $gserver['id']]);
		DBA::update('contact', ['gsid' => $gserver['id'], 'failed' => 0], ['id' => 45]);

		$module = $this->createModule();

		$request = (new ServerRequest('GET', 'https://friendica.local/api/v2/search'))
			->withQueryParams(['q' => 'friendcontact']);

		try {
			$module->handleRequest($request);
			self::fail('Expected EarlyExitException');
		} catch (EarlyExitException $e) { // @phpstan-ignore catch.neverThrown
			$json = $this->toJson($e->getResponse());

			self::assertNotEmpty($json->accounts);

			foreach ($json->accounts as $account) {
				self::assertStringContainsStringIgnoringCase('friendcontact', $account->acct);
			}
		}
	}

	public function testApiSearchReturnsStatuses(): void
	{
		DBA::insert('tag', [
			'id'   => 1000,
			'name' => 'reply',
			'url'  => '',
			'type' => Tag::HASHTAG,
		]);
		DBA::insert('post-tag', [
			'uri-id' => 7,
			'type'   => Tag::HASHTAG,
			'tid'    => 1000,
			'cid'    => 0,
		]);

		$module = $this->createModule();

		$request = (new ServerRequest('GET', 'https://friendica.local/api/v2/search'))
			->withQueryParams(['q' => '#reply']);

		try {
			$module->handleRequest($request);
			self::fail('Expected EarlyExitException');
		} catch (EarlyExitException $e) { // @phpstan-ignore catch.neverThrown
			$json = $this->toJson($e->getResponse());

			self::assertNotEmpty($json->statuses);
			self::assertCount(1, $json->statuses);
			self::assertEquals('7', $json->statuses[0]->id);
		}
	}

	public function testApiSearchWithoutQueryReturnsUnprocessableEntity(): void
	{
		$module = $this->createModule();

		$request = new ServerRequest('GET', 'https://friendica.local/api/v2/search');

		try {
			$module->handleRequest($request);
			self::fail('Expected EarlyExitException');
		} catch (EarlyExitException $e) { // @phpstan-ignore catch.neverThrown
			self::assertEquals(422, $e->getResponse()->getStatusCode());
		}
	}

	public function testApiSearchWithUnallowedUserReturnsUnauthorized(): void
	{
		AuthTestConfig::$authenticated = false;

		$module = $this->createModule();

		$request = (new ServerRequest('GET', 'https://friendica.local/api/v2/search'))
			->withQueryParams(['q' => 'test']);

		try {
			$module->handleRequest($request);
			self::fail('Expected EarlyExitException');
		} catch (EarlyExitException $e) { // @phpstan-ignore catch.neverThrown
			self::assertEquals(401, $e->getResponse()->getStatusCode());
		}
	}

	private function createModule(): Search
	{
		return new Search(DI::mstdnError(), DI::appHelper(), DI::l10n(), DI::baseUrl(), DI::args(), DI::logger(), DI::profiler(), DI::apiResponse(), [], ['version' => 2]);
	}
}
