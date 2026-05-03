<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\Unit\App;

use Friendica\App\BaseURL;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\Config\Model\ReadOnlyFileConfig;
use Friendica\Core\Config\ValueObject\Cache;
use Friendica\Network\HTTPException\InternalServerErrorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BaseURLTest extends TestCase
{
	public static function dataSystemUrl(): array
	{
		return [
			'default' => [
				'input'     => ['system' => ['url' => 'https://friendica.local',],],
				'server'    => [],
				'expect' => 'https://friendica.local',
			],
			'subPath' => [
				'input'     => ['system' => ['url' => 'https://friendica.local/subpath',],],
				'server'    => [],
				'expect' => 'https://friendica.local/subpath',
			],
			'empty' => [
				'input'     => [],
				'server'    => [],
				'expect' => 'http://localhost',
			],
			'serverArrayStandard' => [
				'input'  => [],
				'server' => [
					'HTTPS'        => 'on',
					'HTTP_HOST'    => 'friendica.server',
					'SERVER_PORT'  => 443,
					'REQUEST_URI'  => '/test/it?with=query',
					'QUERY_STRING' => 'pagename=test/it',
				],
				'expect' => 'https://friendica.server',
			],
			'serverArraySubPath' => [
				'input'  => [],
				'server' => [
					'HTTPS'        => 'on',
					'HTTP_HOST'    => 'friendica.server',
					'SERVER_PORT'  => 443,
					'REQUEST_URI'  => '/test/it/now?with=query',
					'QUERY_STRING' => 'pagename=it/now',
				],
				'expect' => 'https://friendica.server/test',
			],
			'serverArraySubPath2' => [
				'input'  => [],
				'server' => [
					'HTTPS'        => 'on',
					'HTTP_HOST'    => 'friendica.server',
					'SERVER_PORT'  => 443,
					'REQUEST_URI'  => '/test/it/now?with=query',
					'QUERY_STRING' => 'pagename=now',
				],
				'expect' => 'https://friendica.server/test/it',
			],
			'serverArraySubPath3' => [
				'input'  => [],
				'server' => [
					'HTTPS'        => 'on',
					'HTTP_HOST'    => 'friendica.server',
					'SERVER_PORT'  => 443,
					'REQUEST_URI'  => '/test/it/now?with=query',
					'QUERY_STRING' => 'pagename=test/it/now',
				],
				'expect' => 'https://friendica.server',
			],
			'serverArrayWithoutQueryString1' => [
				'input'  => [],
				'server' => [
					'HTTPS'       => 'on',
					'HTTP_HOST'   => 'friendica.server',
					'SERVER_PORT' => 443,
					'REQUEST_URI' => '/test/it/now?with=query',
				],
				'expect' => 'https://friendica.server/test/it/now',
			],
			'serverArrayWithoutQueryString2' => [
				'input'  => [],
				'server' => [
					'HTTPS'       => 'on',
					'HTTP_HOST'   => 'friendica.server',
					'SERVER_PORT' => 443,
					'REQUEST_URI' => '',
				],
				'expect' => 'https://friendica.server',
			],
			'serverArrayWithoutQueryString3' => [
				'input'  => [],
				'server' => [
					'HTTPS'       => 'on',
					'HTTP_HOST'   => 'friendica.server',
					'SERVER_PORT' => 443,
					'REQUEST_URI' => '/',
				],
				'expect' => 'https://friendica.server',
			],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('dataSystemUrl')]
	public function testDetermine(array $input, array $server, string $expect)
	{
		// Fixture for empty test
		if ($input === [] && $server === [])
		{
			$server = ['HTTP_HOST' => 'localhost', 'SERVER_PORT' => 80];
		}

		$origServerGlobal = $_SERVER;

		$_SERVER = array_merge($_SERVER, $server);

		$config = self::createStub(IManageConfigValues::class);
		$config->method('get')->willReturnCallback(function(string $category, string $key, mixed $default) use($input): mixed {
			if (!array_key_exists($category, $input))
			{
				return $default;
			}

			if (!array_key_exists($key, $input[$category]))
			{
				return $default;
			}

			return $input[$category][$key];
		});

		$baseUrl = new BaseURL($config, new NullLogger(), $server);

		self::assertEquals($expect, (string) $baseUrl);

		$_SERVER = $origServerGlobal;
	}

	public static function dataRemove(): array
	{
		return [
			'same' => [
				'base'      => ['system' => ['url' => 'https://friendica.local',],],
				'origUrl'   => 'https://friendica.local/test/picture.png',
				'expect' => 'test/picture.png',
			],
			'other' => [
				'base'      => ['system' => ['url' => 'https://friendica.local',],],
				'origUrl'   => 'https://friendica.other/test/picture.png',
				'expect' => 'https://friendica.other/test/picture.png',
			],
			'samSubPath' => [
				'base'      => ['system' => ['url' => 'https://friendica.local/test',],],
				'origUrl'   => 'https://friendica.local/test/picture.png',
				'expect' => 'picture.png',
			],
			'otherSubPath' => [
				'base'      => ['system' => ['url' => 'https://friendica.local/test',],],
				'origUrl'   => 'https://friendica.other/test/picture.png',
				'expect' => 'https://friendica.other/test/picture.png',
			],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('dataRemove')]
	public function testRemove(array $base, string $origUrl, string $expect)
	{
		$config  = new ReadOnlyFileConfig(new Cache($base));
		$baseUrl = new BaseURL($config, new NullLogger());

		self::assertEquals($expect, $baseUrl->remove($origUrl));
	}

	/**
	 * Test that redirect to external domains fails
	 */
	public function testRedirectException()
	{
		self::expectException(InternalServerErrorException::class);
		self::expectExceptionMessage('https://friendica.other is not a relative path, please use System::externalRedirect');

		$config = new ReadOnlyFileConfig(new Cache([
			'system' => [
				'url' => 'https://friendica.local',
			],
		]));
		$baseUrl = new BaseURL($config, new NullLogger());
		$baseUrl->redirect('https://friendica.other');
	}
}
