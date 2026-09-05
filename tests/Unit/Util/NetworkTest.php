<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Util;

use Friendica\Util\Network;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NetworkTest extends TestCase
{
	/**
	 * @return array<string, array{0: bool, 1: string, 2: string}>
	 */
	public static function dataAllowedInternalHost(): array
	{
		return [
			// no address configured
			'empty list'             => [false, 'internal.example', ''],
			'blank entries only'     => [false, 'internal.example', ' , , '],
			'empty host, empty list' => [false, '', ''],

			// one address
			'single exact match'        => [true, 'internal.example', 'internal.example'],
			'single case insensitive'   => [true, 'Internal.Example', 'internal.example'],
			'single no match'           => [false, 'evil.example', 'internal.example'],
			'single padded with spaces' => [true, 'internal.example', '   internal.example   '],
			'single wildcard match'     => [true, 'foo.internal.example', '*.internal.example'],
			'single wildcard no match'  => [false, 'internal.example.org', '*.internal.example'],

			// two addresses
			'two, first matches'         => [true, 'a.example', 'a.example, b.example'],
			'two, second matches'        => [true, 'b.example', 'a.example, b.example'],
			'two, none matches'          => [false, 'c.example', 'a.example, b.example'],
			'two, no spaces after comma' => [true, 'b.example', 'a.example,b.example'],

			// three / four addresses
			'four, middle matches'      => [true, 'c.example', 'a.example, b.example, c.example, d.example'],
			'four, last matches'        => [true, 'd.example', 'a.example, b.example, c.example, d.example'],
			'four, none matches'        => [false, 'x.example', 'a.example, b.example, c.example, d.example'],
			'four, wildcard entry hits' => [true, 'db.internal', 'a.example, *.internal, c.example, d.example'],
			'four, extra commas'        => [true, 'c.example', 'a.example,, b.example , ,c.example,d.example'],
		];
	}

	#[DataProvider('dataAllowedInternalHost')]
	public function testIsAllowedInternalHost(bool $expected, string $host, string $allowedList): void
	{
		self::assertSame($expected, Network::isAllowedInternalHost($host, $allowedList));
	}
}
