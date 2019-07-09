<?php

namespace Friendica\Test\src\Content\Text;

use Friendica\App;
use Friendica\BaseObject;
use Friendica\Content\Text\Markdown;
use Friendica\Test\MockedTest;
use Friendica\Util\Profiler;

class MarkdownTest extends MockedTest
{
	protected function setUp()
	{
		parent::setUp();

		$app = \Mockery::mock(App::class);
		BaseObject::setApp($app);

		$profiler = new Profiler();
		$app->shouldReceive('getProfiler')->andReturn($profiler);
	}

	public function dataMarkdown()
	{
		$inputFiles = glob(__DIR__ . '/../../../datasets/content/text/markdown/*.md');

		$data = [];

		foreach ($inputFiles as $file) {
			$data[str_replace('.md', '', $file)] = [
				'input'    => file_get_contents($file),
				'expected' => file_get_contents(str_replace('.md', '.html', $file))
			];
		}

		return $data;
	}

	/**
	 * Test convert different input Markdown text into HTML
	 * @dataProvider dataMarkdown
	 *
	 * @param string $input    The Markdown text to test
	 * @param string $expected The expected HTML output
	 * @throws \Exception
	 */
	public function testConvert($input, $expected)
	{
		$output = Markdown::convert($input);

		$this->assertEquals($expected, $output);
	}
}