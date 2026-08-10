<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Integration\Module\Api;

use Friendica\DI;
use Friendica\Module\Api\ApiResponse;
use Friendica\Test\ApiTestCase;

final class ApiResponseTest extends ApiTestCase
{
	/** Public contact fixture, see tests/Fixtures/api.fixture.php */
	private const CONTACT_ID = 43;

	public function testApiRssExtra(): void
	{
		$response = new ApiResponse(DI::l10n(), DI::args(), DI::logger(), DI::baseUrl(), DI::twitterUser(), []);

		$result = $response->formatData('root_element', 'rss', ['data' => ['key' => 'some_data']], self::CONTACT_ID);

		self::assertStringContainsString('<alternate>https://friendica.local/profile/othercontact</alternate>', $result);
		self::assertStringContainsString('<base>https://friendica.local</base>', $result);
		self::assertStringContainsString('<logo>https://friendica.local/images/friendica-32.png</logo>', $result);
		self::assertStringContainsString('<updated>', $result);
		self::assertStringContainsString('<atom_updated>', $result);
	}

	public function testApiRssExtraWithoutUserInfo(): void
	{
		$response = new ApiResponse(DI::l10n(), DI::args(), DI::logger(), DI::baseUrl(), DI::twitterUser(), []);

		$result = $response->formatData('root_element', 'rss', ['data' => ['key' => 'some_data']], 0);

		self::assertStringContainsString('<key>some_data</key>', $result);
		self::assertStringNotContainsString('<alternate>', $result);
		self::assertStringNotContainsString('<base>', $result);
		self::assertStringNotContainsString('<logo>', $result);
		self::assertStringNotContainsString('<language>', $result);
	}
}
