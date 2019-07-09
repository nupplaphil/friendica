<?php
/**
 * Created by PhpStorm.
 * User: benlo
 * Date: 25/03/19
 * Time: 21:36
 */

namespace Friendica\Test\src\Content;

use Friendica\App;
use Friendica\BaseObject;
use Friendica\Content\Smilies;
use Friendica\Core\Config;
use Friendica\Core\Config\Adapter\IConfigAdapter;
use Friendica\Core\Config\Cache\ConfigCache;
use Friendica\Test\MockedTest;

class SmiliesTest extends MockedTest
{
	protected function setUp()
	{
		parent::setUp();

		$app = \Mockery::mock(App::class);
		BaseObject::setApp($app);

		$configMock = \Mockery::mock(ConfigCache::class);
		$adapter = \Mockery::mock(IConfigAdapter::class);
		$adapter->shouldReceive('isConnected')->andReturn(false);
		Config::init(new Config\Configuration($configMock, $adapter));

		$app->videowidth = 425;
		$app->videoheight = 350;
		$configMock->shouldReceive('get')
			->with('system', 'no_smilies')
			->andReturn(false);
		$configMock->shouldReceive('get')
			->with(false, 'system', 'no_smilies')
			->andReturn(false);
	}

	public function dataLinks()
	{
		return [
			/** @see https://github.com/friendica/friendica/pull/6933 */
			'bug-6933-1' => [
				'data' => '<code>/</code>',
				'smilies' => ['texts' => [], 'icons' => []],
				'expected' => '<code>/</code>',
			],
			'bug-6933-2' => [
				'data' => '<code>code</code>',
				'smilies' => ['texts' => [], 'icons' => []],
				'expected' => '<code>code</code>',
			],
		];
	}

	/**
	 * Test replace smilies in different texts
	 * @dataProvider dataLinks
	 *
	 * @param string $text     Test string
	 * @param array  $smilies  List of smilies to replace
	 * @param string $expected Expected result
	 * @throws \Friendica\Network\HTTPException\InternalServerErrorException
	 */
	public function testReplaceFromArray($text, $smilies, $expected)
	{
		$output = Smilies::replaceFromArray($text, $smilies);
		$this->assertEquals($expected, $output);
	}
}
