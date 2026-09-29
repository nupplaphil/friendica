<?php

// Copyright (C) 2010-2026, the Friendica project
// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Core\Logger\Handler;

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
	/**
	 * A fatal error (like an exhausted memory limit) never passes through the user error handler,
	 * so the fatal error handler has to log it without a previously recorded trace.
	 */
	public function testFatalErrorIsLoggedWithoutPreviousTrace(): void
	{
		$autoload = dirname(__DIR__, 5) . '/vendor/autoload.php';

		$script = <<<'PHP'
			require $argv[1];

			$logger = new class extends \Psr\Log\AbstractLogger {
				public function log($level, string|\Stringable $message, array $context = []): void
				{
					// the exhausted memory is still held while the shutdown functions run
					ini_set('memory_limit', '-1');
					echo "\nRECORD:" . json_encode(['level' => $level, 'message' => (string) $message, 'context' => $context]);
				}
			};

			\Friendica\Core\Logger\Handler\ErrorHandler::register($logger, false, false);

			ini_set('memory_limit', '8M');
			$data = [];
			while (true) {
				$data[] = str_repeat('x', 1000000);
			}
			PHP;

		$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d html_errors=0 -r ' . escapeshellarg($script) . ' ' . escapeshellarg($autoload) . ' 2>&1';

		$output = (string) shell_exec($command);

		$this->assertStringNotContainsString('must not be accessed before initialization', $output);

		$position = strrpos($output, 'RECORD:');
		$record   = $position === false ? null : json_decode(substr($output, $position + strlen('RECORD:')), true);
		$this->assertIsArray($record, 'The fatal error was not logged: ' . $output);
		$this->assertSame('alert', $record['level']);
		$this->assertStringContainsString('Allowed memory size', $record['message']);
		$this->assertArrayHasKey('trace', $record['context']);
		$this->assertNull($record['context']['trace']);
	}
}
