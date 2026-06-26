<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\src\Module\Api\Mastodon;

use Friendica\Test\ApiTestCase;

class SearchTest extends ApiTestCase
{
	/**
	 * Test the api_search() function.
	 *
	 */
	public function testApiSearch(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		/*
		$_REQUEST['q']      = 'reply';
		$_REQUEST['max_id'] = 10;
		$result             = api_search('json');
		foreach ($result['status'] as $status) {
			self::assertStatus($status);
			self::assertStringContainsStringIgnoringCase('reply', $status['text'], '', true);
		}
		*/
	}

	/**
	 * Test the api_search() function a count parameter.
	 *
	 */
	public function testApiSearchWithCount(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		/*
		$_REQUEST['q']     = 'reply';
		$_REQUEST['count'] = 20;
		$result            = api_search('json');
		foreach ($result['status'] as $status) {
			self::assertStatus($status);
			self::assertStringContainsStringIgnoringCase('reply', $status['text'], '', true);
		}
		*/
	}

	/**
	 * Test the api_search() function with an rpp parameter.
	 *
	 */
	public function testApiSearchWithRpp(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		/*
		$_REQUEST['q']   = 'reply';
		$_REQUEST['rpp'] = 20;
		$result          = api_search('json');
		foreach ($result['status'] as $status) {
			self::assertStatus($status);
			self::assertStringContainsStringIgnoringCase('reply', $status['text'], '', true);
		}
		*/
	}

	/**
	 * Test the api_search() function with an q parameter contains hashtag.
	 */
	#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
	public function testApiSearchWithHashtag(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		/*
		$_REQUEST['q'] = '%23friendica';
		$result        = api_search('json');
		foreach ($result['status'] as $status) {
			self::assertStatus($status);
			self::assertStringContainsStringIgnoringCase('#friendica', $status['text'], '', true);
		}
		*/
	}

	/**
	 * Test the api_search() function with an exclude_replies parameter.
	 */
	#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
	public function testApiSearchWithExcludeReplies(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		/*
		$_REQUEST['max_id']          = 10;
		$_REQUEST['exclude_replies'] = true;
		$_REQUEST['q']               = 'friendica';
		$result                      = api_search('json');
		foreach ($result['status'] as $status) {
			self::assertStatus($status);
		}
		*/
	}

	/**
	 * Test the api_search() function without an authenticated user.
	 *
	 */
	public function testApiSearchWithUnallowedUser(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		// $this->expectException(\Friendica\Network\HTTPException\UnauthorizedException::class);
		// BasicAuth::setCurrentUserID();
		// api_search('json');
	}

	/**
	 * Test the api_search() function without any GET query parameter.
	 *
	 */
	public function testApiSearchWithoutQuery(): never
	{
		self::markTestIncomplete('Needs Search to not set header during call (like at BaseApi::setLinkHeader');

		// $this->expectException(\Friendica\Network\HTTPException\BadRequestException::class);
		// api_search('json');
	}
}
