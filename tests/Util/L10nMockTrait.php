<?php

namespace Friendica\Test\Util;

use Friendica\Core\L10n;
use Mockery\MockInterface;

trait L10nMockTrait
{
	/**
	 * @var MockInterface The interface for L10n mocks
	 */
	private static $l10nMock;

	/**
	 * Mocking the 'L10n::t()' method
	 *
	 * @param null|string $input Either an input (string) or null for EVERY input is possible
	 * @param null|int $times How often will it get called
	 * @param null|string $return Either an return (string) or null for return the input
	 */
	public static function mockL10nT($input = null, $times = null, $return = null)
	{
		if (!isset(self::$l10nMock)) {
			self::$l10nMock = \Mockery::mock('alias:' . L10n::class);

			self::$l10nMock->shouldReceive('init')
			               ->andReturn(true);
		}

		$with = isset($input) ? $input : \Mockery::any();

		$return = isset($return) ? $return : $with;

		if ($return instanceof \Mockery\Matcher\Any) {
			self::$l10nMock
				->shouldReceive('t')
				->with($with)
				->times($times)
				->andReturnUsing(function($arg) { return $arg; });
		} else {
			self::$l10nMock
				->shouldReceive('t')
				->with($with)
				->times($times)
				->andReturn($return);
		}
	}
}
