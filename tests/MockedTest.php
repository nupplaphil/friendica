<?php

namespace Friendica\Test;

use Friendica\Test\Util\L10nMockTrait;
use PHPUnit\Framework\TestCase;

/**
 * This class verifies each mock after each call
 */
abstract class MockedTest extends TestCase
{
	use L10nMockTrait;

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		self::mockL10nT();
	}

	protected function tearDown()
	{
		\Mockery::close();

		parent::tearDown();
	}
}
