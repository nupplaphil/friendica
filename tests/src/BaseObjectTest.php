<?php
/**
 * BaseObjectTest class.
 */

namespace Friendica\Test\src;

use Friendica\App;
use Friendica\BaseObject;
use Friendica\Test\Util\AppMockTrait;
use Friendica\Test\Util\VFSTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the BaseObject class.
 */
class BaseObjectTest extends TestCase
{
	/**
	 * Test the setApp() and getApp() function.
	 * @return void
	 */
	public function testGetSetApp()
	{
		$app = \Mockery::mock(App::class);

		BaseObject::setApp($app);
		$this->assertEquals($app, BaseObject::getApp());
	}

	/**
	 * Test the getApp() function without App
	 * @expectedException Friendica\Network\HTTPException\InternalServerErrorException
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testGetAppFailed()
	{
		BaseObject::getApp();
	}
}
