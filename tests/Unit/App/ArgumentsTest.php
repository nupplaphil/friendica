<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Test\Unit\App;

use Friendica\App;
use PHPUnit\Framework\TestCase;

class ArgumentsTest extends TestCase
{
	private function assertArguments(array $assert, App\Arguments $arguments)
	{
		self::assertEquals($assert['queryString'], $arguments->getQueryString());
		self::assertEquals($assert['command'], $arguments->getCommand());
		self::assertEquals($assert['argv'], $arguments->getArgv());
		self::assertEquals($assert['argc'], $arguments->getArgc());
		self::assertEquals($assert['method'], $arguments->getMethod());
		self::assertCount($assert['argc'], $arguments->getArgv());
	}

	/**
	 * Test the default argument without any determinations
	 */
	public function testDefault(): void
	{
		$arguments = new App\Arguments();

		self::assertArguments(
			[
				'queryString' => '',
				'command'     => '',
				'argv'        => [],
				'argc'        => 0,
				'method'      => App\Router::GET,
			],
			$arguments,
		);
	}

	public static function dataArguments()
	{
		return [
			'withPagename' => [
				'server' => [
					'QUERY_STRING' => 'pagename=profile/test/it&arg1=value1&arg2=value2',
				],
				'get' => [
					'pagename' => 'profile/test/it',
				],
				'expect' => [
					'queryString' => 'profile/test/it?arg1=value1&arg2=value2',
					'command'     => 'profile/test/it',
					'argv'        => ['profile', 'test', 'it'],
					'argc'        => 3,
					'method'      => App\Router::GET,
				],
			],
			'withUnixHomeDir' => [
				'server' => [
					'QUERY_STRING' => 'pagename=~test/it&arg1=value1&arg2=value2',
				],
				'get' => [
					'pagename' => '~test/it',
				],
				'expect' => [
					'queryString' => '~test/it?arg1=value1&arg2=value2',
					'command'     => '~test/it',
					'argv'        => ['~test', 'it'],
					'argc'        => 2,
					'method'      => App\Router::GET,
				],
			],
			'withDiasporaHomeDir' => [
				'server' => [
					'QUERY_STRING' => 'pagename=u/test/it&arg1=value1&arg2=value2',
				],
				'get' => [
					'pagename' => 'u/test/it',
				],
				'expect' => [
					'queryString' => 'u/test/it?arg1=value1&arg2=value2',
					'command'     => 'u/test/it',
					'argv'        => ['u', 'test', 'it'],
					'argc'        => 3,
					'method'      => App\Router::GET,
				],
			],
			'withTrailingSlash' => [
				'server' => [
					'QUERY_STRING' => 'pagename=profile/test/it&arg1=value1&arg2=value2/',
				],
				'get' => [
					'pagename' => 'profile/test/it',
				],
				'expect' => [
					'queryString' => 'profile/test/it?arg1=value1&arg2=value2%2F',
					'command'     => 'profile/test/it',
					'argv'        => ['profile', 'test', 'it'],
					'argc'        => 3,
					'method'      => App\Router::GET,
				],
			],
			'withWrongQueryString' => [
				'server' => [
					'QUERY_STRING' => 'wrong=profile/test/it&arg1=value1&arg2=value2/',
				],
				'get' => [
					'pagename' => 'profile/test/it',
				],
				'expect' => [
					'queryString' => 'profile/test/it?wrong=profile%2Ftest%2Fit&arg1=value1&arg2=value2%2F',
					'command'     => 'profile/test/it',
					'argv'        => ['profile', 'test', 'it'],
					'argc'        => 3,
					'method'      => App\Router::GET,
				],
			],
			'withMissingPageName' => [
				'server' => [
					'QUERY_STRING' => 'pagename=notvalid/it&arg1=value1&arg2=value2/',
				],
				'get' => [
				],
				'expect' => [
					'queryString' => 'notvalid/it?arg1=value1&arg2=value2%2F',
					'command'     => 'notvalid/it',
					'argv'        => ['notvalid', 'it'],
					'argc'        => 2,
					'method'      => App\Router::GET,
				],
			],
			'withNothing' => [
				'server' => [
					'QUERY_STRING' => 'arg1=value1&arg2=value2/',
				],
				'get' => [
				],
				'expect' => [
					'queryString' => '?arg1=value1&arg2=value2%2F',
					'command'     => '',
					'argv'        => [],
					'argc'        => 0,
					'method'      => App\Router::GET,
				],
			],
			'withFileExtension' => [
				'server' => [
					'QUERY_STRING' => 'pagename=api/call.json',
				],
				'get' => [
					'pagename' => 'api/call.json',
				],
				'expect' => [
					'queryString' => 'api/call.json',
					'command'     => 'api/call.json',
					'argv'        => ['api', 'call.json'],
					'argc'        => 2,
					'method'      => App\Router::GET,
				],
			],
			'withHTTPMethod' => [
				'server' => [
					'QUERY_STRING'   => 'pagename=api/call.json',
					'REQUEST_METHOD' => App\Router::POST,
				],
				'get' => [
					'pagename' => 'api/call.json',
				],
				'expect' => [
					'queryString' => 'api/call.json',
					'command'     => 'api/call.json',
					'argv'        => ['api', 'call.json'],
					'argc'        => 2,
					'method'      => App\Router::POST,
				],
			],
		];
	}

	/**
	 * Test all variants of argument determination
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('dataArguments')]
	public function testDetermine(array $server, array $get, array $expect): void
	{
		$arguments = (new App\Arguments())
			->determine($server, $get);

		self::assertArguments($expect, $arguments);
	}

	/**
	 * Test if the get/has methods are working for the determined arguments
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('dataArguments')]
	public function testGetHas(array $server, array $get, array $expect): void
	{
		$arguments = (new App\Arguments())
			->determine($server, $get);

		for ($i = 0; $i < $arguments->getArgc(); $i++) {
			self::assertTrue($arguments->has($i));
			self::assertEquals($expect['argv'][$i], $arguments->get($i));
		}

		self::assertFalse($arguments->has($arguments->getArgc()));
		self::assertEmpty($arguments->get($arguments->getArgc()));
		self::assertEquals('default', $arguments->get($arguments->getArgc(), 'default'));
	}

	public static function dataStripped()
	{
		return [
			'strippedZRLFirst' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&zrl=nope&arg1=value1'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1',
			],
			'strippedZRLLast' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&arg1=value1&zrl=nope'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1',
			],
			'strippedZTLMiddle' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&arg1=value1&zrl=nope&arg2=value2'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1&arg2=value2',
			],
			'strippedOWTFirst' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&owt=test&arg1=value1'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1',
			],
			'strippedOWTLast' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&arg1=value1&owt=test'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1',
			],
			'strippedOWTMiddle' => [
				'server' => ['QUERY_STRING' => 'pagename=test/it&arg1=value1&owt=test&arg2=value2'],
				'get'    => ['pagename' => 'test/it'],
				'expect' => 'test/it?arg1=value1&arg2=value2',
			],
		];
	}

	/**
	 * Test the ZRL and OWT stripping
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('dataStripped')]
	public function testStrippedQueries(array $server, array $get, string $expect): void
	{
		$arguments = (new App\Arguments())->determine($server, $get);

		self::assertEquals($expect, $arguments->getQueryString());
	}

	/**
	 * Test that arguments are immutable
	 */
	public function testImmutable(): void
	{
		$argument = new App\Arguments();

		$argNew = $argument->determine([], []);

		self::assertNotSame($argument, $argNew);
	}

	/**
	 * Test the getQueryParam method with various query parameters
	 */
	public function testGetQueryParam(): void
	{
		// Test with simple query parameter
		$arguments = new App\Arguments('arg1=value1&arg2=value2', 'test', 'test', ['test'], 1);
		self::assertEquals('value1', $arguments->getQueryParam('arg1'));
		self::assertEquals('value2', $arguments->getQueryParam('arg2'));
		self::assertNull($arguments->getQueryParam('nonexistent'));
	}

	/**
	 * Test getQueryParam with empty query string
	 */
	public function testGetQueryParamEmpty(): void
	{
		$arguments = new App\Arguments('', '', '', [], 0);
		self::assertNull($arguments->getQueryParam('any'));
	}

	/**
	 * Test getQueryParam with URL-encoded parameters
	 */
	public function testGetQueryParamEncoded(): void
	{
		$arguments = new App\Arguments('param=hello%20world&other=test%2Bvalue', 'test', 'test', ['test'], 1);
		self::assertEquals('hello world', $arguments->getQueryParam('param'));
		self::assertEquals('test+value', $arguments->getQueryParam('other'));
	}

	/**
	 * Test getQueryParam with special characters
	 */
	public function testGetQueryParamSpecialChars(): void
	{
		$arguments = new App\Arguments('email=test%40example.com&name=John%20Doe', 'test', 'test', ['test'], 1);
		self::assertEquals('test@example.com', $arguments->getQueryParam('email'));
		self::assertEquals('John Doe', $arguments->getQueryParam('name'));
	}

	/**
	 * Test getQueryParam with multiple values for same parameter (last value should be returned)
	 * Note: parse_str() with duplicate parameter names will use the last value
	 */
	public function testGetQueryParamMultipleValues(): void
	{
		$arguments = new App\Arguments('param=value1&param=value2', 'test', 'test', ['test'], 1);
		// parse_str() uses the last value when parameter names are duplicated
		self::assertEquals('value2', $arguments->getQueryParam('param'));
	}

	/**
	 * Test getQueryParam with empty parameter value
	 */
	public function testGetQueryParamEmptyValue(): void
	{
		$arguments = new App\Arguments('param=&other=value', 'test', 'test', ['test'], 1);
		self::assertEquals('', $arguments->getQueryParam('param'));
		self::assertEquals('value', $arguments->getQueryParam('other'));
	}
}
